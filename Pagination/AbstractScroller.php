<?php

namespace Keboola\Juicer\Pagination;

abstract class AbstractScroller
{
    /**
     * AbstractScroller constructor.
     * @param array $config
     */
    public function __construct(array $config)
    {
    }

    /**
     * Get object vars by default
     */
    public function getState()
    {
        return get_object_vars($this);
    }

    /**
     * @param array $state
     */
    public function setState(array $state)
    {
        foreach (array_keys(get_object_vars($this)) as $key) {
            if (isset($state[$key])) {
                $this->{$key} = $state[$key];
            }
        }
    }
}
