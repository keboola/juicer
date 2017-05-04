<?php

namespace Keboola\Juicer\Pagination;

use Keboola\Juicer\Client\ClientInterface;
use Keboola\Juicer\Config\JobConfig;

/**
 * For extractors with no pagination
 */
class NoScroller implements ScrollerInterface
{
    /**
     * {@inheritdoc}
     */
    public function getFirstRequest(ClientInterface $client, JobConfig $jobConfig)
    {
        return $client->createRequest($jobConfig->getConfig());
    }

    /**
     * {@inheritdoc}
     */
    public function getNextRequest(ClientInterface $client, JobConfig $jobConfig, $response, $data)
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function reset()
    {
    }

    /**
     * {@inheritdoc}
     */
    public static function create(array $config)
    {
        return new self;
    }

    public function getState()
    {
        return [];
    }

    public function setState(array $state)
    {
    }
}
