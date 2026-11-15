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

    protected ?string $classExtends = null;

    /**
     * @var array<string, list<string>>
     */
    protected array $classPropertyTypeHints = [];

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

        if ($node instanceof Stmt\Class_) {
            $this->classExtends = $node->extends !== null
                ? $this->resolveName($node->extends)
                : null;

            $this->hydratePropertyTypeHints($node->getDocComment()?->getText());
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

        if ($node instanceof Expr\PropertyFetch) {
            return $this->inferTypesFromPropertyFetch($node);
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

            $resolved[] = $this->resolveSingleTypeString($part);
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

        $shortName = $this->shortName($type);

        if (in_array($shortName, ['UuidInterface', 'Uuid'], true)) {
            return 'string';
        }

        return $this->resolveName($type) ?? $type;
    }

    protected function isJsonResourceClass(): bool
    {
        return in_array(
            $this->shortName($this->classExtends ?? ''),
            ['JsonResource', 'ResourceCollection'],
            true,
        ) || ($this->classFqcn !== null
            && is_subclass_of($this->classFqcn, \Illuminate\Http\Resources\Json\JsonResource::class));
    }

    protected function getPropertyHintTypes(string $propertyName): array
    {
        return $this->classPropertyTypeHints[$propertyName] ?? ['mixed'];
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

        if ($this->shortName($resolvedClass) === 'Arr' && $method === 'get') {
            return $this->inferTypesFromArrGetCall($node);
        }

        if (
            in_array($this->shortName($resolvedClass), ['Str', 'Uuid'], true)
            && in_array($method, ['uuid', 'orderedUuid', 'uuid4', 'uuid7'], true)
        ) {
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
    private function inferTypesFromArrGetCall(Expr\StaticCall $node): array
    {
        $keyArgument = $node->args[1]->value ?? null;

        if ($keyArgument instanceof String_ && $this->isUuidLikeName($keyArgument->value)) {
            return ['string'];
        }

        $defaultArgument = $node->args[2]->value ?? null;

        if ($defaultArgument instanceof Expr) {
            return $this->inferTypes($defaultArgument);
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

    protected function isUuidLikeName(string $name): bool
    {
        $normalized = strtolower(preg_replace('/[^a-z0-9]+/', '', $name) ?? $name);

        return $normalized === 'uuid' || str_ends_with($normalized, 'uuid');
    }

    /**
     * @return list<string>
     */
    private function inferTypesFromPropertyFetch(Expr\PropertyFetch $node): array
    {
        if (!$node->name instanceof Node\Identifier) {
            return ['mixed'];
        }

        $propertyName = $node->name->toString();

        if (isset($this->classPropertyTypeHints[$propertyName])) {
            return $this->classPropertyTypeHints[$propertyName];
        }

        if (
            $node->var instanceof Expr\PropertyFetch
            && $node->var->name instanceof Node\Identifier
            && $node->var->name->toString() === 'resource'
            && isset($this->classPropertyTypeHints[$propertyName])
        ) {
            return $this->classPropertyTypeHints[$propertyName];
        }

        return ['mixed'];
    }

    private function hydratePropertyTypeHints(?string $docComment): void
    {
        $this->classPropertyTypeHints = [];

        if ($docComment === null) {
            return;
        }

        if (preg_match_all(
            '/@property(?:-read|-write)?\s+([^\s]+)\s+\$([\w]+)/i',
            $docComment,
            $matches,
            PREG_SET_ORDER,
        ) === 0) {
            return;
        }

        foreach ($matches as $match) {
            $this->classPropertyTypeHints[$match[2]] = $this->resolveTypeString($match[1] ?? 'mixed');
        }
    }

    private function resolveSingleTypeString(string $type): string
    {
        $type = trim($type);

        if ($type === '') {
            return 'mixed';
        }

        $arrayDepth = 0;

        while (str_ends_with($type, '[]')) {
            $arrayDepth++;
            $type = substr($type, 0, -2);
        }

        $normalized = match (true) {
            str_starts_with($type, 'array{') => $this->convertShapeToTypeScript($type, 'array'),
            str_starts_with($type, 'object{') => $this->convertShapeToTypeScript($type, 'object'),
            preg_match('/^(array|list|Collection)\s*<(.+)>$/is', $type, $generic) === 1 => $this->convertGenericCollectionType($generic[2]),
            strtolower($type) === 'collection' => 'mixed[]',
            default => $this->normalizeTypeToken($type),
        };

        while ($arrayDepth > 0) {
            $normalized = $this->wrapArrayType($normalized);
            $arrayDepth--;
        }

        return $normalized;
    }

    private function convertGenericCollectionType(string $arguments): string
    {
        $parts = $this->splitTopLevel($arguments, ',');
        $types = $this->resolveTypeString((string) array_pop($parts));
        $value = $this->renderInlineUnion($types, useUnknownForMixed: true);

        return match (strtolower(trim($parts[0] ?? ''))) {
            'string' => 'Record<string, ' . $value . '>',
            'array-key' => 'Record<string, ' . $value . '> | Array<' . $value . '>',
            default => $this->wrapArrayType(count($types) === 1 && $types[0] !== 'mixed' ? $types[0] : $value),
        };
    }

    private function convertShapeToTypeScript(string $type, string $shapeKind): string
    {
        $start = strpos($type, '{');

        if ($start === false) {
            return $shapeKind === 'array' ? 'array' : 'object';
        }

        $shape = $this->extractDelimitedContent(substr($type, $start), '{', '}');
        $definitions = $this->splitTopLevel($shape, ',');

        if ($shapeKind === 'array' && count($this->splitTopLevel($shape, ':')) === 1) {
            $elements = array_filter(array_map('trim', $definitions), 'strlen');

            return '[' . implode(', ', array_map(
                fn (string $element): string => $this->renderInlineUnion($this->resolveTypeString($element), useUnknownForMixed: true),
                $elements,
            )) . ']';
        }

        $fields = [];

        foreach ($definitions as $definition) {
            $definition = trim($definition);

            if ($definition === '') {
                continue;
            }

            [$fieldName, $fieldType] = $this->splitFieldDefinition($definition);

            if ($fieldName === null || $fieldType === null) {
                continue;
            }

            $optional = str_ends_with($fieldName, '?');
            $fieldName = rtrim($fieldName, '?');
            $fields[] = sprintf(
                '%s%s: %s',
                $fieldName,
                $optional ? '?' : '',
                $this->renderInlineUnion($this->resolveTypeString($fieldType)),
            );
        }

        return '{ ' . implode('; ', $fields) . ' }';
    }

    /**
     * @param list<string> $types
     */
    private function renderInlineUnion(array $types, bool $useUnknownForMixed = false): string
    {
        $rendered = array_values(array_unique(array_map(
            fn (string $type): string => $useUnknownForMixed && $type === 'mixed'
                ? 'unknown'
                : $this->convertInlineTypeToTypeScript($type),
            $types,
        )));

        return implode(' | ', $rendered === [] ? ['any'] : $rendered);
    }

    private function convertInlineTypeToTypeScript(string $type): string
    {
        if (str_starts_with($type, '{ ') || str_starts_with($type, 'Array<')) {
            return $type;
        }

        if (str_ends_with($type, '[]')) {
            return $this->wrapArrayType($this->convertInlineTypeToTypeScript(substr($type, 0, -2)));
        }

        return match ($type) {
            'int', 'integer', 'float', 'double' => 'number',
            'bool', 'boolean' => 'boolean',
            'mixed' => 'any',
            default => str_contains($type, '\\') ? $this->shortName($type) : $type,
        };
    }

    private function wrapArrayType(string $type): string
    {
        return strpbrk($type, ' |<') !== false || str_starts_with($type, '[')
            ? 'Array<' . $type . '>'
            : $type . '[]';
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function splitFieldDefinition(string $definition): array
    {
        $buffer = '';
        $depth = 0;
        $length = strlen($definition);

        for ($index = 0; $index < $length; $index++) {
            $character = $definition[$index];

            if (in_array($character, ['<', '{', '(', '['], true)) {
                $depth++;
            } elseif (in_array($character, ['>', '}', ')', ']'], true) && $depth > 0) {
                $depth--;
            }

            if ($character === ':' && $depth === 0) {
                return [
                    trim($buffer),
                    trim(substr($definition, $index + 1)),
                ];
            }

            $buffer .= $character;
        }

        return [null, null];
    }

    private function extractDelimitedContent(string $value, string $open, string $close): string
    {
        $start = strpos($value, $open);

        if ($start === false) {
            return $value;
        }

        $depth = 0;
        $buffer = '';
        $length = strlen($value);

        for ($index = $start; $index < $length; $index++) {
            $character = $value[$index];

            if ($character === $open) {
                if ($depth > 0) {
                    $buffer .= $character;
                }

                $depth++;

                continue;
            }

            if ($character === $close) {
                $depth--;

                if ($depth === 0) {
                    return $buffer;
                }

                if ($depth > 0) {
                    $buffer .= $character;
                }

                continue;
            }

            if ($depth > 0) {
                $buffer .= $character;
            }
        }

        return $buffer;
    }

    private function resetState(): void
    {
        $this->namespace = '';
        $this->uses = [];
        $this->className = null;
        $this->classFqcn = null;
        $this->classExtends = null;
        $this->classPropertyTypeHints = [];

        $this->resetDefinitionState();
    }

    abstract protected function resetDefinitionState(): void;

    abstract protected function getDefinition(string $name): TypeDefinition;
}
