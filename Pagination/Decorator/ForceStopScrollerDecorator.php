<?php

namespace Keboola\Juicer\Pagination\Decorator;

use Keboola\Juicer\Pagination\ScrollerInterface,
    Keboola\Juicer\Pagination\ScrollerFactory,
    Keboola\Juicer\Client\ClientInterface,
    Keboola\Juicer\Config\JobConfig,
    Keboola\Juicer\Exception\UserException;
use Keboola\Utils\Utils;

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
                $this->volumeLimit = Utils::return_bytes($config['forceStop']['volume']);
            }
        }

        parent::__construct($scroller, $config);

        $this->reset();
    }

    public function getFirstRequest(ClientInterface $client, JobConfig $jobConfig)
    {
        $this->startTime = time();

        return $this->scroller->getFirstRequest($client, $jobConfig);
    }

    /**
     * @param ClientInterface $client
     * @param $jobConfig $jobConfig
     * @param mixed $response
     * @param array $data
     * @return RequestInterface|false
     */
    public function getNextRequest(ClientInterface $client, JobConfig $jobConfig, $response, $data)
    {
        if ($this->checkLimits($response)) {
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
    }

    protected function checkPages()
    {
        if (is_null($this->pageLimit)) {
            return false;
        }

        if (++$this->pageCounter > $this->pageLimit) {
            return true;
        }
    }

    protected function checkTime()
    {
        if (is_null($this->timeLimit)) {
            return false;
        }

        if (($this->startTime + $this->timeLimit) <= time()) {
            return true;
        }
    }

    protected function checkVolume($response)
    {
        if (is_null($this->volumeLimit)) {
            return false;
        }

        $this->volumeCounter += strlen(json_encode($response));
        if ($this->volumeCounter > $this->volumeLimit) {
            return true;
        }
    }

    public function reset()
    {
        $this->pageCounter = 0;
        $this->volumeCounter = 0;
        $this->startTime = time();

        return parent::reset();
    }
}

