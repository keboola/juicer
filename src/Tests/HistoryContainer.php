<?php

declare(strict_types=1);

namespace Keboola\Juicer\Tests;

use ArrayAccess;
use Countable;
use InvalidArgumentException;
use OutOfRangeException;
use UnderflowException;

/**
 * Contains history of the requests in the mocked RestClient
 */
class HistoryContainer implements ArrayAccess, Countable
{
    /** @var HistoryItem[] */
    private array $items = [];

    public function pop(): HistoryItem
    {
        $item = array_pop($this->items);
        if (!$item) {
            throw new UnderflowException('No more history items.');
        }

        return $item;
    }

    public function shift(): HistoryItem
    {
        $item = array_shift($this->items);
        if (!$item) {
            throw new UnderflowException('No more history items.');
        }

        return $item;
    }

    public function first(): HistoryItem
    {
        $item = $this->items[array_key_first($this->items)] ?? null;
        if (!$item) {
            throw new OutOfRangeException('No history items.');
        }

        return $item;
    }

    public function last(): HistoryItem
    {
        $item = $this->items[array_key_last($this->items)] ?? null;
        if (!$item) {
            throw new OutOfRangeException('No history items.');
        }

        return $item;
    }

    public function isEmpty(): bool
    {
        return $this->count() === 0;
    }

    public function clear(): self
    {
        $this->items = [];
        return $this;
    }

    public function count(): int
    {
        return count($this->items);
    }

    /**
     * @param mixed $offset
     */
    public function offsetExists($offset): bool
    {
        return isset($this->items[$offset]);
    }

    /**
     * @param mixed $offset
     */
    public function offsetGet($offset): ?HistoryItem
    {
        return $this->items[$offset] ?? null;
    }

    /**
     * @param mixed $offset
     * @param mixed $array
     */
    public function offsetSet($offset, $array): void
    {
        if (!is_array($array)) {
            throw new InvalidArgumentException('Value must be array.');
        }

        // Map to object
        $value = HistoryItem::fromArray($array);

        if (is_null($offset)) {
            $this->items[] = $value;
        } else {
            $this->items[$offset] = $value;
        }
    }

    /**
     * @param mixed $offset
     */
    public function offsetUnset($offset): void
    {
        unset($this->items[$offset]);
    }
}
