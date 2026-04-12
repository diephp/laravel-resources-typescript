<?php

declare(strict_types=1);

namespace DiePHP\LaravelResourcesTypescript\Parsers;

use DiePHP\LaravelResourcesTypescript\Dtos\TypeDefinition;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\UseUse;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use ReflectionClass;
use Throwable;

abstract class AbstractParser extends NodeVisitorAbstract
{
    protected string $namespace = '';

    /**
     * @var array<string, string>
     */
    protected array $uses = [];

    protected ?string $className = null;

    protected ?string $classFqcn = null;

    public function beforeTraverse(array $nodes): ?array
    {
        $this->resetState();

        return null;
    }

    public function enterNode(Node $node): null|int|Node|array
    {
        if ($node instanceof Stmt\Namespace_) {
            $this->namespace = $node->name?->toString() ?? '';
        }

        if ($node instanceof Stmt\Use_) {
            foreach ($node->uses as $use) {
                $this->registerUseStatement($use);
            }
        }

        if ($node instanceof Stmt\GroupUse) {
            foreach ($node->uses as $use) {
                $this->registerUseStatement($use, $node->prefix->toString());
            }
        }

        if ($node instanceof Stmt\ClassLike && $node->name !== null) {
            $this->className = $node->name->toString();
            $this->classFqcn = $this->resolveClassLikeName($node);
        }

        return null;
    }

    public function process(string $code, ?string $definitionName = null): TypeDefinition
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $ast = $parser->parse($code) ?? [];

        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver());
        $traverser->addVisitor($this);
        $traverser->traverse($ast);

        return $this->getDefinition($definitionName ?? $this->className ?? 'AnonymousType');
    }

    /**
     * @return list<string>
     */
    protected function parseTypeFromString(Node $node): array
    {
        if ($node instanceof String_) {
            return $this->resolveTypeString($node->value);
        }

        return ['mixed'];
    }

    /**
     * @return list<string>
     */
    protected function inferTypes(Node $node): array
    {
        if ($node instanceof Node\Scalar\LNumber) {
            return ['int'];
        }

        if ($node instanceof Node\Scalar\DNumber) {
            return ['float'];
        }

        if ($node instanceof Node\Scalar\String_ || $node instanceof Expr\BinaryOp\Concat) {
            return ['string'];
        }

        if ($node instanceof Expr\Cast\Int_) {
            return ['int'];
        }

        if ($node instanceof Expr\Cast\String_) {
            return ['string'];
        }

        if ($node instanceof Expr\Cast\Double) {
            return ['float'];
        }

        if ($node instanceof Expr\Cast\Bool_) {
            return ['bool'];
        }

        if ($node instanceof Expr\Cast\Array_) {
            return ['array'];
        }

        if ($node instanceof Expr\Cast\Object_) {
            return ['object'];
        }

        if ($node instanceof Expr\ConstFetch) {
            return match ($node->name->toLowerString()) {
                'null' => ['null'],
                'true', 'false' => ['bool'],
                default => ['mixed'],
            };
        }

        if ($node instanceof Expr\Array_) {
            return ['array'];
        }

        if ($node instanceof Expr\New_) {
            return $this->inferTypesFromInstantiation($node);
        }

        if ($node instanceof Expr\StaticCall) {
            return $this->inferTypesFromStaticCall($node);
        }

        if ($node instanceof Expr\ClassConstFetch) {
            return $this->inferTypesFromClassConstant($node);
        }

        if ($node instanceof Expr\Ternary) {
            return $this->mergeTypeLists(
                $node->if !== null ? $this->inferTypes($node->if) : $this->inferTypes($node->cond),
                $this->inferTypes($node->else),
            );
        }

        if ($node instanceof Expr\BinaryOp\Coalesce) {
            return $this->mergeTypeLists($this->inferTypes($node->left), $this->inferTypes($node->right));
        }

        if (
            $node instanceof Expr\BooleanNot
            || $node instanceof Expr\BinaryOp\BooleanAnd
            || $node instanceof Expr\BinaryOp\BooleanOr
            || $node instanceof Expr\BinaryOp\Equal
            || $node instanceof Expr\BinaryOp\NotEqual
            || $node instanceof Expr\BinaryOp\Identical
            || $node instanceof Expr\BinaryOp\NotIdentical
            || $node instanceof Expr\BinaryOp\Greater
            || $node instanceof Expr\BinaryOp\GreaterOrEqual
            || $node instanceof Expr\BinaryOp\Smaller
            || $node instanceof Expr\BinaryOp\SmallerOrEqual
        ) {
            return ['bool'];
        }

        if ($node instanceof Expr\Match_) {
            $types = ['mixed'];

            foreach ($node->arms as $arm) {
                $types = $this->mergeTypeLists($types, $this->inferTypes($arm->body));
            }

            return $types;
        }

        return ['mixed'];
    }

    /**
     * @return list<string>
     */
    protected function resolveTypeString(string $type): array
    {
        $type = trim($type);

        if ($type === '') {
            return ['mixed'];
        }

        if (str_starts_with($type, '?')) {
            return $this->mergeTypeLists(
                $this->resolveTypeString(substr($type, 1)),
                ['null'],
            );
        }

        $resolved = [];

        foreach ($this->splitTopLevel($type, '|') as $part) {
            $part = trim($part);

            if ($part === '') {
                continue;
            }

            $arrayDepth = 0;

            while (str_ends_with($part, '[]')) {
                $arrayDepth++;
                $part = substr($part, 0, -2);
            }

            if (preg_match('/^(array|list)<.*>$/i', $part) === 1) {
                $normalized = 'array';
            } elseif (preg_match('/^(Collection)<.*>$/', $part) === 1) {
                $normalized = 'mixed[]';
            } elseif (str_starts_with($part, 'array{')) {
                $normalized = 'array';
            } else {
                $normalized = $this->normalizeTypeToken($part);
            }

            while ($arrayDepth > 0) {
                $normalized .= '[]';
                $arrayDepth--;
            }

            $resolved[] = $normalized;
        }

        return $resolved === [] ? ['mixed'] : array_values(array_unique($resolved));
    }

    protected function shortName(string $type): string
    {
        $normalized = trim($type, '\\');

        if (!str_contains($normalized, '\\')) {
            return $normalized;
        }

        $parts = explode('\\', $normalized);

        return (string) end($parts);
    }

    protected function isPrimitiveType(string $type): bool
    {
        return in_array(
            $type,
            ['int', 'integer', 'float', 'double', 'string', 'bool', 'boolean', 'array', 'object', 'mixed', 'null', 'true', 'false'],
            true,
        );
    }

    /**
     * @param list<string> $types
     * @return list<string>
     */
    protected function extractClassReferences(array $types): array
    {
        $references = [];

        foreach ($types as $type) {
            $baseType = $type;

            while (str_ends_with($baseType, '[]')) {
                $baseType = substr($baseType, 0, -2);
            }

            if ($this->isPrimitiveType($baseType) || str_contains($baseType, "'")) {
                continue;
            }

            if (str_contains($baseType, '\\')) {
                $references[] = trim($baseType, '\\');
            }
        }

        return array_values(array_unique($references));
    }

    /**
     * @param list<string> $left
     * @param list<string> $right
     * @return list<string>
     */
    protected function mergeTypeLists(array $left, array $right): array
    {
        $merged = array_values(array_unique([...$left, ...$right]));
        $specific = array_values(
            array_filter(
                $merged,
                static fn (string $type): bool => $type !== 'mixed',
            ),
        );

        return $specific === [] ? ['mixed'] : $specific;
    }

    /**
     * @return list<string>
     */
    protected function splitTopLevel(string $value, string $delimiter): array
    {
        $parts = [];
        $buffer = '';
        $depth = 0;

        $length = strlen($value);

        for ($index = 0; $index < $length; $index++) {
            $character = $value[$index];

            if (in_array($character, ['<', '{', '(', '['], true)) {
                $depth++;
            } elseif (in_array($character, ['>', '}', ')', ']'], true) && $depth > 0) {
                $depth--;
            }

            if ($character === $delimiter && $depth === 0) {
                $parts[] = $buffer;
                $buffer = '';

                continue;
            }

            $buffer .= $character;
        }

        $parts[] = $buffer;

        return $parts;
    }

    protected function normalizeTypeToken(string $type): string
    {
        $type = trim($type);

        if ($type === '') {
            return 'mixed';
        }

        $lowerType = strtolower($type);

        if (in_array($lowerType, ['int', 'integer', 'float', 'double', 'string', 'bool', 'boolean', 'array', 'object', 'mixed', 'null', 'true', 'false'], true)) {
            return $lowerType;
        }

        if (in_array($lowerType, ['self', 'static', '$this'], true)) {
            return $this->classFqcn ?? $this->className ?? 'mixed';
        }

        if ($lowerType === 'parent') {
            return 'mixed';
        }

        return $this->resolveName($type) ?? $type;
    }

    protected function resolveName(Name|string|null $name): ?string
    {
        if ($name === null) {
            return null;
        }

        if ($name instanceof Name) {
            $resolvedName = $name->getAttribute('resolvedName');

            if ($resolvedName instanceof Name) {
                return $resolvedName->toString();
            }

            return $this->resolveName($name->toString());
        }

        $trimmed = trim($name, '\\');

        if ($trimmed === '') {
            return null;
        }

        if (str_contains($trimmed, '\\')) {
            return $trimmed;
        }

        if (isset($this->uses[$trimmed])) {
            return $this->uses[$trimmed];
        }

        return $this->namespace !== '' ? $this->namespace . '\\' . $trimmed : $trimmed;
    }

    private function registerUseStatement(UseUse $use, string $prefix = ''): void
    {
        $fqcn = $prefix !== ''
            ? $prefix . '\\' . $use->name->toString()
            : $use->name->toString();

        $alias = $use->alias?->toString() ?? $this->shortName($fqcn);

        $this->uses[$alias] = $fqcn;
    }

    private function resolveClassLikeName(Stmt\ClassLike $node): ?string
    {
        $namespacedName = $node->namespacedName ?? null;

        if ($namespacedName instanceof Name) {
            return $namespacedName->toString();
        }

        return $this->resolveName($node->name?->toString());
    }

    /**
     * @return list<string>
     */
    private function inferTypesFromInstantiation(Expr\New_ $node): array
    {
        if (!$node->class instanceof Name) {
            return ['mixed'];
        }

        $resolvedClass = $this->resolveName($node->class);

        if ($resolvedClass === null) {
            return ['mixed'];
        }

        return match ($this->shortName($resolvedClass)) {
            'Collection' => ['mixed[]'],
            'Carbon' => ['string'],
            default => [trim($resolvedClass, '\\')],
        };
    }

    /**
     * @return list<string>
     */
    private function inferTypesFromStaticCall(Expr\StaticCall $node): array
    {
        if (!$node->class instanceof Name) {
            return ['mixed'];
        }

        $resolvedClass = $this->resolveName($node->class);

        if ($resolvedClass === null) {
            return ['mixed'];
        }

        $method = $node->name instanceof Node\Identifier ? $node->name->toString() : null;

        if ($method === 'collection') {
            return [trim($resolvedClass, '\\') . '[]'];
        }

        if ($method === 'now') {
            return ['string'];
        }

        if ($method !== null && $this->isEnumClass($resolvedClass)) {
            return [trim($resolvedClass, '\\')];
        }

        return ['mixed'];
    }

    /**
     * @return list<string>
     */
    private function inferTypesFromClassConstant(Expr\ClassConstFetch $node): array
    {
        if (
            !$node->class instanceof Name
            || !$node->name instanceof Node\Identifier
        ) {
            return ['mixed'];
        }

        $resolvedClass = $this->resolveName($node->class);

        if ($resolvedClass === null) {
            return ['mixed'];
        }

        if ($node->name->toString() === 'class') {
            return ['string'];
        }

        return $this->isEnumClass($resolvedClass)
            ? [trim($resolvedClass, '\\')]
            : ['mixed'];
    }

    private function isEnumClass(string $className): bool
    {
        if (!class_exists($className) && !enum_exists($className)) {
            return false;
        }

        try {
            return (new ReflectionClass($className))->isEnum();
        } catch (Throwable) {
            return false;
        }
    }

    private function resetState(): void
    {
        $this->namespace = '';
        $this->uses = [];
        $this->className = null;
        $this->classFqcn = null;

        $this->resetDefinitionState();
    }

    abstract protected function resetDefinitionState(): void;

    abstract protected function getDefinition(string $name): TypeDefinition;
}
