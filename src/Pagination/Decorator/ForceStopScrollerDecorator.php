<?php

declare(strict_types=1);

namespace Keboola\Juicer\Pagination\Decorator;

use Keboola\Juicer\Client\RestClient;
use Keboola\Juicer\Client\RestRequest;
use Keboola\Juicer\Config\JobConfig;
use Keboola\Juicer\Pagination\ScrollerInterface;
use Psr\Log\LoggerInterface;

/**
 * Class ForceStopScrollerDecorator
 * Adds 'forceStop' option
 */
class ForceStopScrollerDecorator extends AbstractScrollerDecorator
{
    protected ?int $pageLimit = null;

    /**
     * Time in seconds
     */
    protected ?int $timeLimit = null;

    /**
     * Size in bytes
     */
    protected ?int $volumeLimit = null;

    protected ?int $pageCounter = null;

    protected ?int $volumeCounter = null;

    /**
     * Timestamp
     */
    protected ?int $startTime = null;

    protected bool $limitReached = false;

    private LoggerInterface $logger;

    public function __construct(ScrollerInterface $scroller, array $config, LoggerInterface $logger)
    {
        parent::__construct($scroller);
        if (!empty($config['forceStop'])) {
            if (!empty($config['forceStop']['pages'])) {
                $this->pageLimit = $config['forceStop']['pages'];
            }
            if (!empty($config['forceStop']['time'])) {
                $this->timeLimit = is_int($config['forceStop']['time'])
                    ? $config['forceStop']['time']
                    : (int) strtotime($config['forceStop']['time'], 0);
            }
            if (!empty($config['forceStop']['volume'])) {
                $this->volumeLimit = intval($config['forceStop']['volume']);
            }
        }
        $this->reset();
        $this->logger = $logger;
    }

    /**
     * @inheritdoc
     */
    public function getFirstRequest(RestClient $client, JobConfig $jobConfig): ?RestRequest
    {
        $this->startTime = time();
        $this->pageCounter = 1;

        return $this->scroller->getFirstRequest($client, $jobConfig);
    }

    /**
     * @inheritdoc
     */
    public function getNextRequest(RestClient $client, JobConfig $jobConfig, $response, array $data): ?RestRequest
    {
        if ($this->checkLimits($response)) {
            $this->limitReached = true;
            return null;
        }

        return $this->scroller->getNextRequest($client, $jobConfig, $response, $data);
    }

    /**
     * @param mixed $response
     * @return bool Returns true if a limit is reached
     */
    private function checkLimits($response): bool
    {
        return $this->checkPages() || $this->checkTime() || $this->checkVolume($response);
    }

    /**
     * Uses internal counter to check page limit
     */
    private function checkPages(): bool
    {
        if (is_null($this->pageLimit)) {
            return false;
        }

        if (++$this->pageCounter > $this->pageLimit) {
            $this->logger->info(sprintf(
                'Force stopping: page limit reached (%d pages).',
                $this->pageLimit,
            ));
            return true;
        }
        return false;
    }

    /**
     * Checks time between first and current request
     */
    private function checkTime(): bool
    {
        if (is_null($this->timeLimit)) {
            return false;
        }

        if (($this->startTime + $this->timeLimit) <= time()) {
            $this->logger->info(sprintf(
                'Force stopping: time limit reached (%d seconds).',
                $this->timeLimit,
            ));
            return true;
        }
        return false;
    }

    /**
     * Count the size of $response and check the limit
     * @param object|array $response
     */
    private function checkVolume($response): bool
    {
        if (is_null($this->volumeLimit)) {
            return false;
        }

        $this->volumeCounter += strlen((string) json_encode($response));
        if ($this->volumeCounter > $this->volumeLimit) {
            $this->logger->info(sprintf(
                'Force stopping: volume limit reached (%d bytes).',
                $this->volumeLimit,
            ));
            return true;
        }
        return false;
    }

    /**
     * @inheritdoc
     */
    public function reset(): void
    {
        $this->pageCounter = 0;
        $this->volumeCounter = 0;
        $this->startTime = time();
        parent::reset();
    }

    public function getLimitReached(): bool
    {
        return $this->limitReached;
    }
}
