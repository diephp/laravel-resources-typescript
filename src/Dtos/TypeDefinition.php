<?php

declare(strict_types=1);

namespace DiePHP\LaravelResourcesTypescript\Dtos;

final class TypeDefinition
{
    public const KIND_ALIAS = 'alias';
    public const KIND_INTERFACE = 'interface';

    /**
     * @var array<string, FieldDefinition>
     */
    private array $fields = [];

    /**
     * @var list<string>
     */
    private array $aliasTypes = [];

    /**
     * @var list<string>
     */
    private array $references = [];

    private ?string $rawTypeScriptType = null;

    public function __construct(
        private string $name,
        private string $kind = self::KIND_INTERFACE,
    ) {
    }

    public static function interface(string $name): self
    {
        return new self($name, self::KIND_INTERFACE);
    }

    public static function alias(string $name, array|string $types = ['mixed']): self
    {
        $definition = new self($name, self::KIND_ALIAS);
        $definition->setAliasTypes($types);

        return $definition;
    }

    public static function rawAlias(string $name, string $typeScriptType): self
    {
        $definition = new self($name, self::KIND_ALIAS);
        $definition->rawTypeScriptType = $typeScriptType;

        return $definition;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getKind(): string
    {
        return $this->kind;
    }

    /**
     * @return array<string, FieldDefinition>
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * @return list<string>
     */
    public function getAliasTypes(): array
    {
        return $this->aliasTypes;
    }

    public function getRawTypeScriptType(): ?string
    {
        return $this->rawTypeScriptType;
    }

    /**
     * @return list<string>
     */
    public function getReferences(): array
    {
        return $this->references;
    }

    public function addField(string $name, array|string $types, bool $optional = false): void
    {
        $this->addFieldDefinition($name, new FieldDefinition($types, $optional));
    }

    public function addFieldDefinition(string $name, FieldDefinition $definition): void
    {
        $this->kind = self::KIND_INTERFACE;
        $this->rawTypeScriptType = null;
        $this->aliasTypes = [];

        if (isset($this->fields[$name])) {
            $this->fields[$name] = $this->fields[$name]->merge($definition);

            return;
        }

        $this->fields[$name] = $definition;
    }

    public function setAliasTypes(array|string $types): void
    {
        $normalizedTypes = FieldDefinition::normalizeTypes($types);

        if ($this->kind === self::KIND_INTERFACE && $this->fields !== []) {
            return;
        }

        $this->kind = self::KIND_ALIAS;
        $this->rawTypeScriptType = null;
        $this->aliasTypes = $normalizedTypes;
    }

    public function addReference(string $reference): void
    {
        $reference = trim($reference, '\\');

        if ($reference === '') {
            return;
        }

        $this->references[] = $reference;
        $this->references = array_values(array_unique($this->references));
    }

    /**
     * @param list<string> $references
     */
    public function addReferences(array $references): void
    {
        foreach ($references as $reference) {
            $this->addReference($reference);
        }
    }

    public function merge(self $other): self
    {
        $result = clone $this;
        $result->addReferences($other->getReferences());

        if ($other->kind === self::KIND_INTERFACE && $other->fields !== []) {
            if ($result->kind !== self::KIND_INTERFACE && !$result->hasConcreteAlias()) {
                $result->kind = self::KIND_INTERFACE;
                $result->aliasTypes = [];
                $result->rawTypeScriptType = null;
            }

            if ($result->kind === self::KIND_INTERFACE) {
                foreach ($other->fields as $name => $definition) {
                    $result->addFieldDefinition($name, $definition);
                }
            }
        }

        if ($other->kind === self::KIND_ALIAS) {
            if ($result->kind !== self::KIND_INTERFACE || $result->fields === []) {
                $result->kind = self::KIND_ALIAS;

                if ($other->rawTypeScriptType !== null) {
                    $result->rawTypeScriptType = $other->rawTypeScriptType;
                    $result->aliasTypes = [];
                } elseif (
                    $result->rawTypeScriptType === null
                    && !$result->hasConcreteAlias()
                ) {
                    $result->aliasTypes = FieldDefinition::normalizeTypes([
                        ...$result->aliasTypes,
                        ...$other->aliasTypes,
                    ]);
                }
            }
        }

        return $result;
    }

    public function isEmpty(): bool
    {
        return $this->fields === []
            && $this->aliasTypes === []
            && $this->rawTypeScriptType === null;
    }

    private function hasConcreteAlias(): bool
    {
        return $this->rawTypeScriptType !== null
            || (
                $this->aliasTypes !== []
                && !($this->aliasTypes === ['mixed'])
            );
    }
}
