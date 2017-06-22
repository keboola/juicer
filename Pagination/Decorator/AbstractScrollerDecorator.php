<?php

namespace Keboola\Juicer\Pagination\Decorator;

use Keboola\Juicer\Client\RestClient;
use Keboola\Juicer\Client\RestRequest;
use Keboola\Juicer\Pagination\ScrollerInterface;
use Keboola\Juicer\Pagination\ScrollerFactory;
use Keboola\Juicer\Config\JobConfig;

/**
 * @todo $config should be the config for the Decorator itself
 */
abstract class AbstractScrollerDecorator implements ScrollerInterface
{
    /**
     * @var ScrollerInterface
     */
    protected $scroller;

    /**
     * @var array
     */
    protected $config;

    public function __construct(ScrollerInterface $scroller, array $config)
    {
        $this->scroller = $scroller;
        $this->config = $config;
    }

    /**
     * @param RestClient $client
     * @param $jobConfig $jobConfig
     * @return RestRequest|false
     */
    public function getFirstRequest(RestClient $client, JobConfig $jobConfig)
    {
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
        return $this->scroller->getNextRequest($client, $jobConfig, $response, $data);
    }

    /**
     * Reset the pagination pointer
     */
    public function reset()
    {
        return $this->scroller->reset();
    }

    /**
     * @deprecated
     * @param array $config
     * @return ScrollerInterface
     */
    public static function create(array $config)
    {
        return ScrollerFactory::getScroller($config);
    }

    public function getScroller()
    {
        return $this->scroller;
    }

    /**
     * Get object vars by default
     */
    public function getState()
    {
        return [
            'decorator' => get_object_vars($this),
            'scroller' => get_object_vars($this->scroller)
        ];
    }

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
