<?php


namespace StackTrace\Inspec;


use Illuminate\Support\Arr;

class ArrayBuilder extends \ArrayObject
{
    /**
     * Set the given array key only when the value is not considered empty.
     */
    public function setUnlessEmpty(string $key, $value): static
    {
        if (! empty($value)) {
            Arr::set($this, $key, $value instanceof ArrayBuilder ? (array) $value : $value);
        }

        return $this;
    }

    public function merge(array $object): static
    {
        foreach ($object as $key => $value) {
            $this[$key] = $value instanceof ArrayBuilder ? (array) $value : $value;
        }

        return $this;
    }

    public static function make(array $value = []): static
    {
        return new static($value);
    }
}
