<?php

namespace Keboola\Juicer\Pagination\Decorator;

use Keboola\Juicer\Client\RestClient;
use Keboola\Juicer\Client\RestRequest;
use Keboola\Juicer\Pagination\ScrollerInterface;
use Keboola\Juicer\Config\JobConfig;

/**
 * Adds 'forceStop' option
 * config:
 * pagination:
 *   forceStop:
 *     field: hasMore #name of the bool field
 */
class ForceStopScrollerDecorator extends AbstractScrollerDecorator
{
    /**
     * @var int
     */
    protected $pageLimit;
    /**
     * seconds
     * @var int
     */
    protected $timeLimit;
    /**
     * bytes
     * @var int
     */
    protected $volumeLimit;

    /**
     * @var int
     */
    protected $pageCounter;
    /**
     * @var int
     */
    protected $volumeCounter;
    /**
     * timestamp
     * @var int
     */
    protected $startTime;

    /**
     * @var bool
     */
    protected $limitReached = false;

    public function __construct(ScrollerInterface $scroller, array $config)
    {
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

        parent::__construct($scroller, $config);

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
     * @param RestClient $client
     * @param $jobConfig $jobConfig
     * @param mixed $response
     * @param array $data
     * @return RestRequest|false
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
     * @return bool|null Returns true if a limit is reached
     */
    protected function checkLimits($response)
    {
        if ($this->checkPages() || $this->checkTime() || $this->checkVolume($response)) {
            return true;
        }
        return null;
    }

    /**
     * Uses internal counter to check page limit
     * @return bool
     */
    protected function checkPages()
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
    protected function checkTime()
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
    protected function checkVolume($response)
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

    public function reset()
    {
        $this->pageCounter = 0;
        $this->volumeCounter = 0;
        $this->startTime = time();

        return parent::reset();
    }

    /**
     * @return bool
     */
    public function getLimitReached()
    {
        return $this->limitReached;
    }
}
