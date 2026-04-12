<?php

declare(strict_types=1);

namespace DiePHP\LaravelResourcesTypescript\Parsers;

use DiePHP\LaravelResourcesTypescript\Dtos\FieldDefinition;
use DiePHP\LaravelResourcesTypescript\Dtos\TypeDefinition;
use PhpParser\Node;
use PhpParser\Node\Expr;

class ToArrayParser extends AbstractParser
{
    private TypeDefinition $definition;

    public function enterNode(Node $node): null|int|Node|array
    {
        parent::enterNode($node);

        if (
            !$node instanceof Node\Stmt\ClassMethod
            || $node->name->toString() !== 'toArray'
        ) {
            return null;
        }

        $returns = $this->analyzeStatements($node->getStmts() ?? [], []);

        foreach ($returns['definitions'] as $returnedDefinition) {
            $this->definition = $this->definition->isEmpty()
                ? $this->copyDefinition($returnedDefinition)
                : $this->mergeDefinitions($this->definition, $returnedDefinition);
        }

        return null;
    }

    protected function resetDefinitionState(): void
    {
        $this->definition = TypeDefinition::interface('AnonymousType');
    }

    protected function getDefinition(string $name): TypeDefinition
    {
        return TypeDefinition::interface($name)->merge($this->definition);
    }

    /**
     * @param array<int, Node\Stmt> $statements
     * @param array<string, TypeDefinition> $variables
     * @return array{variables: array<string, TypeDefinition>, definitions: list<TypeDefinition>}
     */
    private function analyzeStatements(array $statements, array $variables): array
    {
        $definitions = [];

        foreach ($statements as $statement) {
            if (
                $statement instanceof Node\Stmt\Expression
                && $statement->expr instanceof Expr\Assign
            ) {
                $variables = $this->handleAssignment($statement->expr, $variables);

                continue;
            }

            if ($statement instanceof Node\Stmt\If_) {
                $branchResult = $this->analyzeIfStatement($statement, $variables);
                $variables = $branchResult['variables'];
                $definitions = [...$definitions, ...$branchResult['definitions']];

                continue;
            }

            if ($statement instanceof Node\Stmt\Return_) {
                $definitions[] = $this->definitionFromExpression($statement->expr, $variables);

                continue;
            }
        }

        return [
            'variables' => $variables,
            'definitions' => $definitions,
        ];
    }

    /**
     * @param array<string, TypeDefinition> $variables
     * @return array<string, TypeDefinition>
     */
    private function handleAssignment(Expr\Assign $assignment, array $variables): array
    {
        if ($assignment->var instanceof Expr\Variable && is_string($assignment->var->name)) {
            $variables[$assignment->var->name] = $this->definitionFromExpression($assignment->expr, $variables);

            return $variables;
        }

        if ($assignment->var instanceof Expr\ArrayDimFetch) {
            $variableName = $this->extractVariableName($assignment->var->var);
            $fieldName = $this->extractArrayKey($assignment->var->dim);

            if ($variableName === null || $fieldName === null) {
                return $variables;
            }

            $variableDefinition = $variables[$variableName] ?? TypeDefinition::interface($variableName);

            if ($variableDefinition->getKind() !== TypeDefinition::KIND_INTERFACE) {
                $variableDefinition = TypeDefinition::interface($variableName);
            }

            $types = $this->inferTypesWithVariables($assignment->expr, $variables);

            $variableDefinition->addField($fieldName, $types);
            $variableDefinition->addReferences($this->extractClassReferences($types));
            $variables[$variableName] = $variableDefinition;
        }

        return $variables;
    }

    /**
     * @param array<string, TypeDefinition> $variables
     * @return array{variables: array<string, TypeDefinition>, definitions: list<TypeDefinition>}
     */
    private function analyzeIfStatement(Node\Stmt\If_ $statement, array $variables): array
    {
        $thenResult = $this->analyzeStatements($statement->stmts, $this->copyVariables($variables));
        $branchVariables = [$thenResult['variables']];
        $definitions = $thenResult['definitions'];

        foreach ($statement->elseifs as $elseif) {
            $elseifResult = $this->analyzeStatements($elseif->stmts, $this->copyVariables($variables));
            $branchVariables[] = $elseifResult['variables'];
            $definitions = [...$definitions, ...$elseifResult['definitions']];
        }

        if ($statement->else !== null) {
            $elseResult = $this->analyzeStatements($statement->else->stmts, $this->copyVariables($variables));
            $branchVariables[] = $elseResult['variables'];
            $definitions = [...$definitions, ...$elseResult['definitions']];
        } else {
            $branchVariables[] = $this->copyVariables($variables);
        }

        return [
            'variables' => $this->mergeVariableStates($branchVariables, $variables),
            'definitions' => $definitions,
        ];
    }

    /**
     * @param Expr|null $expression
     * @param array<string, TypeDefinition> $variables
     */
    private function definitionFromExpression(?Expr $expression, array $variables): TypeDefinition
    {
        if ($expression === null) {
            return TypeDefinition::alias('AnonymousType', 'mixed');
        }

        if ($expression instanceof Expr\Array_) {
            return $this->definitionFromArray($expression, $variables);
        }

        if ($expression instanceof Expr\Variable && is_string($expression->name) && isset($variables[$expression->name])) {
            return $this->copyDefinition($variables[$expression->name]);
        }

        $types = $this->inferTypesWithVariables($expression, $variables);
        $definition = TypeDefinition::alias('AnonymousType', $types);
        $definition->addReferences($this->extractClassReferences($types));

        return $definition;
    }

    /**
     * @param array<string, TypeDefinition> $variables
     */
    private function definitionFromArray(Expr\Array_ $array, array $variables): TypeDefinition
    {
        $definition = TypeDefinition::interface('AnonymousType');

        foreach ($array->items as $item) {
            if ($item === null) {
                continue;
            }

            $fieldName = $this->extractArrayKey($item->key);

            if ($fieldName === null) {
                continue;
            }

            $types = $this->inferTypesWithVariables($item->value, $variables);
            $definition->addField($fieldName, $types);
            $definition->addReferences($this->extractClassReferences($types));
        }

        return $definition;
    }

    /**
     * @param array<string, TypeDefinition> $variables
     * @return list<string>
     */
    private function inferTypesWithVariables(Expr $expression, array $variables): array
    {
        if ($expression instanceof Expr\Variable && is_string($expression->name)) {
            if (!isset($variables[$expression->name])) {
                return ['mixed'];
            }

            $definition = $variables[$expression->name];

            return $definition->getKind() === TypeDefinition::KIND_ALIAS
                ? $definition->getAliasTypes()
                : ['array'];
        }

        if ($expression instanceof Expr\ArrayDimFetch) {
            $variableName = $this->extractVariableName($expression->var);
            $fieldName = $this->extractArrayKey($expression->dim);

            if (
                $variableName !== null
                && $fieldName !== null
                && isset($variables[$variableName])
            ) {
                $definition = $variables[$variableName];

                if (
                    $definition->getKind() === TypeDefinition::KIND_INTERFACE
                    && isset($definition->getFields()[$fieldName])
                ) {
                    return $definition->getFields()[$fieldName]->getTypes();
                }
            }
        }

        return $this->inferTypes($expression);
    }

    /**
     * @param array<int, array<string, TypeDefinition>> $branchVariables
     * @param array<string, TypeDefinition> $baseVariables
     * @return array<string, TypeDefinition>
     */
    private function mergeVariableStates(array $branchVariables, array $baseVariables): array
    {
        $merged = [];
        $variableNames = [];

        foreach ($branchVariables as $variables) {
            $variableNames = [...$variableNames, ...array_keys($variables)];
        }

        $variableNames = array_values(array_unique($variableNames));

        foreach ($variableNames as $name) {
            $definitions = [];

            foreach ($branchVariables as $variables) {
                $definitions[] = $variables[$name] ?? null;
            }

            $merged[$name] = $this->mergeVariableDefinitions($name, $definitions, $baseVariables[$name] ?? null);
        }

        return $merged;
    }

    /**
     * @param array<int, TypeDefinition|null> $definitions
     */
    private function mergeVariableDefinitions(string $name, array $definitions, ?TypeDefinition $baseDefinition): TypeDefinition
    {
        $existingDefinitions = array_values(array_filter($definitions));

        if ($existingDefinitions === []) {
            return $baseDefinition ?? TypeDefinition::alias($name, 'mixed');
        }

        $allInterfaces = array_reduce(
            $existingDefinitions,
            static fn (bool $carry, TypeDefinition $definition): bool => $carry && $definition->getKind() === TypeDefinition::KIND_INTERFACE,
            true,
        );

        if ($allInterfaces) {
            $merged = TypeDefinition::interface($name);
            $fieldMap = [];

            foreach ($definitions as $definition) {
                $fields = $definition?->getFields() ?? [];

                foreach ($fields as $fieldName => $fieldDefinition) {
                    if (!isset($fieldMap[$fieldName])) {
                        $fieldMap[$fieldName] = [];
                    }

                    $fieldMap[$fieldName][] = $fieldDefinition;
                }
            }

            $totalBranches = count($definitions);

            foreach ($fieldMap as $fieldName => $fieldDefinitions) {
                /** @var FieldDefinition $fieldDefinition */
                $fieldDefinition = array_shift($fieldDefinitions);

                foreach ($fieldDefinitions as $otherDefinition) {
                    $fieldDefinition = $fieldDefinition->merge($otherDefinition);
                }

                if (count($fieldDefinitions) + 1 < $totalBranches) {
                    $fieldDefinition = $fieldDefinition->markOptional();
                }

                $merged->addFieldDefinition($fieldName, $fieldDefinition);
                $merged->addReferences($this->extractClassReferences($fieldDefinition->getTypes()));
            }

            return $merged;
        }

        $types = ['mixed'];

        foreach ($existingDefinitions as $definition) {
            $definitionTypes = $definition->getKind() === TypeDefinition::KIND_ALIAS
                ? $definition->getAliasTypes()
                : ['array'];

            $types = $this->mergeTypeLists($types, $definitionTypes);
        }

        $merged = TypeDefinition::alias($name, $types);
        $merged->addReferences($this->extractClassReferences($types));

        return $merged;
    }

    private function mergeDefinitions(TypeDefinition $left, TypeDefinition $right): TypeDefinition
    {
        if ($left->isEmpty()) {
            return $this->copyDefinition($right);
        }

        if ($right->isEmpty()) {
            return $this->copyDefinition($left);
        }

        if (
            $left->getKind() === TypeDefinition::KIND_INTERFACE
            && $right->getKind() === TypeDefinition::KIND_INTERFACE
        ) {
            $merged = TypeDefinition::interface($left->getName());
            $fieldNames = array_values(array_unique([
                ...array_keys($left->getFields()),
                ...array_keys($right->getFields()),
            ]));

            foreach ($fieldNames as $fieldName) {
                $leftField = $left->getFields()[$fieldName] ?? null;
                $rightField = $right->getFields()[$fieldName] ?? null;

                if ($leftField !== null && $rightField !== null) {
                    $fieldDefinition = $leftField->merge($rightField);
                } else {
                    $fieldDefinition = ($leftField ?? $rightField)?->markOptional();
                }

                if ($fieldDefinition !== null) {
                    $merged->addFieldDefinition($fieldName, $fieldDefinition);
                    $merged->addReferences($this->extractClassReferences($fieldDefinition->getTypes()));
                }
            }

            $merged->addReferences($left->getReferences());
            $merged->addReferences($right->getReferences());

            return $merged;
        }

        return $left->merge($right);
    }

    /**
     * @param array<string, TypeDefinition> $variables
     * @return array<string, TypeDefinition>
     */
    private function copyVariables(array $variables): array
    {
        $copied = [];

        foreach ($variables as $name => $definition) {
            $copied[$name] = $this->copyDefinition($definition);
        }

        return $copied;
    }

    private function copyDefinition(TypeDefinition $definition): TypeDefinition
    {
        $copied = $definition->getKind() === TypeDefinition::KIND_INTERFACE
            ? TypeDefinition::interface($definition->getName())
            : (
                $definition->getRawTypeScriptType() !== null
                    ? TypeDefinition::rawAlias($definition->getName(), $definition->getRawTypeScriptType())
                    : TypeDefinition::alias($definition->getName(), $definition->getAliasTypes())
            );

        foreach ($definition->getFields() as $fieldName => $fieldDefinition) {
            $copied->addFieldDefinition($fieldName, new FieldDefinition($fieldDefinition->getTypes(), $fieldDefinition->isOptional()));
        }

        $copied->addReferences($definition->getReferences());

        return $copied;
    }

    private function extractVariableName(Expr $expression): ?string
    {
        return $expression instanceof Expr\Variable && is_string($expression->name)
            ? $expression->name
            : null;
    }

    private function extractArrayKey(Node|Expr|null $key): ?string
    {
        if ($key instanceof Node\Scalar\String_) {
            return $key->value;
        }

        if ($key instanceof Node\Scalar\Int_) {
            return (string) $key->value;
        }

        return null;
    }
}
