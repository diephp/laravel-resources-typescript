<?php

namespace DiePHP\LaravelResourcesTypescript\Dtos;

use ArrayAccess;
use Iterator;
use ReturnTypeWillChange;

class GodDto implements ArrayAccess, Iterator
{

    public $a = 1;

    public function __construct()
    {
    }

    // 🟢 Magic property getter
    public function __get($name)
    {
        return new self();
    }

    // 🟢 Magic property setter (ignored)
    public function __set($name, $value)
    {
        return new self();
    }

    // 🟢 Magic method caller
    public function __call($name, $arguments)
    {
        return new self();
    }

    // 🟢 Static method caller
    public static function __callStatic($name, $arguments)
    {
        return new self();
    }

    // 🟢 Convert to string (prevents "Object could not be converted to string" warnings)
    public function __toString()
    {
        return "0";
    }

    public function __toInt()
    {
        return 0;
    }

    public function toInt()
    {
        return 0;
    }

    // 🟢 Convert to int (fixes "Object could not be converted to int")
    public function __invoke()
    {
        return 0;
    }

    // 🟢 Handles cases like `(int) $godDto`
    public function __serialize(): array
    {
        return [];
    }

    public function __unserialize(array $data): void
    {
    }

    // 🟢 Implements ArrayAccess
    public function offsetExists($offset): bool
    {
        return false;
    }

    #[ReturnTypeWillChange] public function offsetGet($offset)
    {
        return new self();
    }

    public function offsetSet($offset, $value): void
    {
    }

    public function offsetUnset($offset): void
    {
    }

    // 🟢 Implements Iterator (always empty)
    #[ReturnTypeWillChange] public function current()
    {
        return new self();
    }

    #[ReturnTypeWillChange] public function key()
    {
        return new self();
    }

    public function next(): void
    {
    }

    public function rewind(): void
    {
    }

    public function valid(): bool
    {
        return false;
    }

    public function __debugInfo()
    {
        return [];
    }
}
