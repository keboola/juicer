<?php

declare(strict_types=1);

namespace Keboola\Juicer\Pagination;

use Psr\Log\LoggerInterface;

abstract class AbstractScroller implements ScrollerInterface
{
    protected LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Get object vars by default
     */
    public function getState(): array
    {
        return get_object_vars($this);
    }

    public function setState(array $state): void
    {
        foreach (array_keys(get_object_vars($this)) as $key) {
            if (isset($state[$key])) {
                $this->{$key} = $state[$key];
            }
        }
    }
}
