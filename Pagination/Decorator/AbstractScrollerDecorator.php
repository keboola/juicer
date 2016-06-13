<?php

namespace Keboola\Juicer\Pagination\Decorator;

use Keboola\Juicer\Pagination\ScrollerInterface,
    Keboola\Juicer\Pagination\ScrollerFactory,
    Keboola\Juicer\Client\ClientInterface,
    Keboola\Juicer\Config\JobConfig,
    Keboola\Juicer\Exception\UserException;

/**
 * @todo $config should be the config for the Decorator itself
 */
abstract class AbstractScrollerDecorator implements ScrollerInterface
{
    /**
     * @var ScrollerInterface
     */
    protected $scroller;

    public function __construct(ScrollerInterface $scroller, array $config)
    {
        $this->scroller = $scroller;
    }

        /**
     * @param ClientInterface $client
     * @param $jobConfig $jobConfig
     * @return RequestInterface|false
     */
    public function getFirstRequest(ClientInterface $client, JobConfig $jobConfig)
    {
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
     */
    public static function create(array $config)
    {
        return ScrollerFactory::getScroller($config);
    }

    public function getScroller()
    {
        return $this->scroller;
    }
}

