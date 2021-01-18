<?php

declare(strict_types=1);

namespace Keboola\Juicer\Retry;

use GuzzleHttp\Middleware;
use Psr\Log\LoggerInterface;

class RetryMiddlewareFactory
{
    private LoggerInterface $logger;

    private array $retryConfig;

    public function __construct(LoggerInterface $logger, array $retryConfig)
    {
        $this->logger = $logger;
        $this->retryConfig = $retryConfig;
    }

    public function create(): callable
    {
        $retryHandler = new RetryHandler($this->logger, $this->retryConfig);
        return Middleware::retry([$retryHandler, 'decider'], [$retryHandler, 'delay']);
    }
}
