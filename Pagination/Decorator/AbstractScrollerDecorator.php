<?php

namespace Keboola\Juicer\Pagination\Decorator;

use Keboola\Juicer\Client\RestClient;
use Keboola\Juicer\Pagination\ScrollerInterface;
use Keboola\Juicer\Config\JobConfig;

abstract class AbstractScrollerDecorator implements ScrollerInterface
{
    /**
     * @var ScrollerInterface
     */
    protected $scroller;

    /**
     * AbstractScrollerDecorator constructor.
     * @param ScrollerInterface $scroller
     */
    public function __construct(ScrollerInterface $scroller)
    {
        $this->scroller = $scroller;
    }

    public function __clone()
    {
        $this->scroller = clone $this->scroller;
    }

    /**
     * @inheritdoc
     */
    public function getFirstRequest(RestClient $client, JobConfig $jobConfig)
    {
        return $this->scroller->getFirstRequest($client, $jobConfig);
    }

    /**
     * @inheritdoc
     */
    public function getNextRequest(RestClient $client, JobConfig $jobConfig, $response, $data)
    {
        return $this->scroller->getNextRequest($client, $jobConfig, $response, $data);
    }

    /**
     * @inheritdoc
     */
    public function reset()
    {
        $this->scroller->reset();
    }

    /**
     * Get decorated scroller
     * @return ScrollerInterface
     */
    public function getScroller()
    {
        return $this->scroller;
    }

    /**
     * @inheritdoc
     */
    public function getState()
    {
        return [
            'decorator' => get_object_vars($this),
            'scroller' => get_object_vars($this->scroller)
        ];
    }

    /**
     * @inheritdoc
     */
    public function setState(array $state)
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
