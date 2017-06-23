<?php

namespace Keboola\Juicer\Pagination\Decorator;

use Keboola\Juicer\Client\RestClient;
use Keboola\Juicer\Client\RestRequest;
use Keboola\Juicer\Pagination\ScrollerInterface;
use Keboola\Juicer\Config\JobConfig;

/**
 * Class ForceStopScrollerDecorator
 * Adds 'forceStop' option
 */
class ForceStopScrollerDecorator extends AbstractScrollerDecorator
{
    /**
     * @var int|null
     */
    protected $pageLimit = null;

    /**
     * Time in seconds
     * @var int|null
     */
    protected $timeLimit = null;

    /**
     * Size in bytes
     * @var int|null
     */
    protected $volumeLimit = null;

    /**
     * @var int
     */
    protected $pageCounter;

    /**
     * @var int
     */
    protected $volumeCounter;

    /**
     * Timestamp
     * @var int
     */
    protected $startTime;

    /**
     * @var bool
     */
    protected $limitReached = false;

    /**
     * ForceStopScrollerDecorator constructor.
     * @param ScrollerInterface $scroller
     * @param array $config
     */
    public function __construct(ScrollerInterface $scroller, array $config)
    {
        parent::__construct($scroller);
        if (!empty($config['forceStop'])) {
            if (!empty($config['forceStop']['pages'])) {
                $this->pageLimit = $config['forceStop']['pages'];
            }
            if (!empty($config['forceStop']['time'])) {
                $this->timeLimit = is_int($config['forceStop']['time'])
                    ? $config['forceStop']['time']
                    : strtotime($config['forceStop']['time'], 0);
            }
            if (!empty($config['forceStop']['volume'])) {
                $this->volumeLimit = intval($config['forceStop']['volume']);
            }
        }
        $this->reset();
    }

    /**
     * @param RestClient $client
     * @param $jobConfig $jobConfig
     * @return RestRequest
     */
    public function getFirstRequest(RestClient $client, JobConfig $jobConfig)
    {
        $this->startTime = time();
        $this->pageCounter = 1;

        return $this->scroller->getFirstRequest($client, $jobConfig);
    }

    /**
     * @inheritdoc
     */
    public function getNextRequest(RestClient $client, JobConfig $jobConfig, $response, $data)
    {
        if ($this->checkLimits($response)) {
            $this->limitReached = true;
            return false;
        }

        return $this->scroller->getNextRequest($client, $jobConfig, $response, $data);
    }

    /**
     * @param mixed $response
     * @return bool Returns true if a limit is reached
     */
    private function checkLimits($response)
    {
        if ($this->checkPages() || $this->checkTime() || $this->checkVolume($response)) {
            return true;
        }
        return false;
    }

    /**
     * Uses internal counter to check page limit
     * @return bool
     */
    private function checkPages()
    {
        if (is_null($this->pageLimit)) {
            return false;
        }

        if (++$this->pageCounter > $this->pageLimit) {
            return true;
        }
        return false;
    }

    /**
     * Checks time between first and current request
     * @return bool
     */
    private function checkTime()
    {
        if (is_null($this->timeLimit)) {
            return false;
        }

        if (($this->startTime + $this->timeLimit) <= time()) {
            return true;
        }
        return false;
    }

    /**
     * Count the size of $response and check the limit
     * @param object|array $response
     * @return bool
     */
    private function checkVolume($response)
    {
        if (is_null($this->volumeLimit)) {
            return false;
        }

        $this->volumeCounter += strlen(json_encode($response));
        if ($this->volumeCounter > $this->volumeLimit) {
            return true;
        }
        return false;
    }

    /**
     * @inheritdoc
     */
    public function reset()
    {
        $this->pageCounter = 0;
        $this->volumeCounter = 0;
        $this->startTime = time();
        parent::reset();
    }

    /**
     * @return bool
     */
    public function getLimitReached()
    {
        return $this->limitReached;
    }
}
