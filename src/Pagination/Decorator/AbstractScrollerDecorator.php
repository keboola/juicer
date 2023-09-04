<?php

declare(strict_types=1);

namespace Keboola\Juicer\Pagination\Decorator;

use Keboola\Juicer\Client\RestClient;
use Keboola\Juicer\Client\RestRequest;
use Keboola\Juicer\Config\JobConfig;
use Keboola\Juicer\Pagination\ScrollerInterface;
use Psr\Log\LoggerInterface;

abstract class AbstractScrollerDecorator implements ScrollerInterface
{
    protected ScrollerInterface $scroller;

    protected LoggerInterface $logger;

    public function __construct(ScrollerInterface $scroller, LoggerInterface $logger)
    {
        $this->scroller = $scroller;
        $this->logger = $logger;
    }

    public function __clone()
    {
        $this->scroller = clone $this->scroller;
        $this->reset();
    }

    /**
     * @inheritdoc
     */
    public function getFirstRequest(RestClient $client, JobConfig $jobConfig): ?RestRequest
    {
        return $this->scroller->getFirstRequest($client, $jobConfig);
    }

    /**
     * @inheritdoc
     */
    public function getNextRequest(RestClient $client, JobConfig $jobConfig, $response, array $data): ?RestRequest
    {
        return $this->scroller->getNextRequest($client, $jobConfig, $response, $data);
    }

    /**
     * @inheritdoc
     */
    public function reset(): void
    {
        $this->scroller->reset();
    }

    /**
     * Get decorated scroller
     */
    public function getScroller(): ScrollerInterface
    {
        return $this->scroller;
    }

    /**
     * @inheritdoc
     */
    public function getState(): array
    {
        return [
            'decorator' => get_object_vars($this),
            'scroller' => get_object_vars($this->scroller),
        ];
    }

    /**
     * @inheritdoc
     */
    public function setState(array $state): void
    {
        if (isset($state['scroller'])) {
            $this->scroller->setState($state['scroller']);
        }

        foreach (array_keys(get_object_vars($this)) as $key) {
            if (isset($state['decorator'][$key])) {
                $this->{$key} = $state['decorator'][$key];
            }
        }
    }
}
