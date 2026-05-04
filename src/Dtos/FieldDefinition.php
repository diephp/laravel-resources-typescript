<?php

declare(strict_types=1);

namespace DiePHP\LaravelResourcesTypescript\Dtos;

final class FieldDefinition
{
    /**
     * @var list<string>
     */
    private array $types;

    public function __construct(array|string $types = ['mixed'], private bool $optional = false)
    {
        $this->types = self::normalizeTypes($types);
    }

    /**
     * @return list<string>
     */
    public function getTypes(): array
    {
        return $this->types;
    }

    public function isOptional(): bool
    {
        return $this->optional;
    }

    public function markOptional(): self
    {
        $clone = clone $this;
        $clone->optional = true;

        return $clone;
    }

    public function merge(self $other): self
    {
        return new self(
            self::mergeTypes($this->types, $other->types),
            $this->optional && $other->optional,
        );
    }

    /**
     * @param array<string>|string $types
     * @return list<string>
     */
    public static function normalizeTypes(array|string $types): array
    {
        $values = is_array($types) ? $types : self::splitTopLevel((string) $types, '|');
        $normalized = [];

        foreach ($values as $type) {
            foreach (self::splitTopLevel((string) $type, '|') as $item) {
                $trimmed = trim($item);

                if ($trimmed === '') {
                    continue;
                }

                $normalized[] = $trimmed === 'any' ? 'mixed' : $trimmed;
            }
        }

        $unique = array_values(array_unique($normalized));

        return $unique === [] ? ['mixed'] : $unique;
    }

    /**
     * @param list<string> $left
     * @param list<string> $right
     * @return list<string>
     */
    private static function mergeTypes(array $left, array $right): array
    {
        $merged = array_values(array_unique([...$left, ...$right]));
        $specificTypes = array_values(
            array_filter(
                $merged,
                static fn (string $type): bool => $type !== 'mixed',
            ),
        );

        if ($specificTypes === []) {
            return ['mixed'];
        }

        if (in_array('array', $specificTypes, true) && self::hasMoreSpecificTypeThanArray($specificTypes)) {
            $specificTypes = array_values(array_filter(
                $specificTypes,
                static fn (string $type): bool => $type !== 'array',
            ));
        }

        if (in_array('object', $specificTypes, true) && self::hasSpecificObjectType($specificTypes)) {
            $specificTypes = array_values(array_filter(
                $specificTypes,
                static fn (string $type): bool => $type !== 'object',
            ));
        }

        return $specificTypes === [] ? ['mixed'] : $specificTypes;
    }

    /**
     * @param list<string> $types
     */
    private static function hasMoreSpecificTypeThanArray(array $types): bool
    {
        foreach ($types as $type) {
            if (
                $type !== 'array'
                && $type !== 'mixed'
                && $type !== 'null'
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $types
     */
    private static function hasSpecificObjectType(array $types): bool
    {
        foreach ($types as $type) {
            if ($type !== 'object' && str_starts_with($type, '{ ')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private static function splitTopLevel(string $value, string $delimiter): array
    {
        $parts = [];
        $buffer = '';
        $depth = 0;
        $inSingleQuote = false;
        $escaped = false;
        $length = strlen($value);

        for ($index = 0; $index < $length; $index++) {
            $character = $value[$index];

            if ($inSingleQuote) {
                $buffer .= $character;

                if ($escaped) {
                    $escaped = false;

                    continue;
                }

                if ($character === '\\') {
                    $escaped = true;

                    continue;
                }

                if ($character === "'") {
                    $inSingleQuote = false;
                }

                continue;
            }

            if ($character === "'") {
                $inSingleQuote = true;
                $buffer .= $character;

                continue;
            }

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
}
