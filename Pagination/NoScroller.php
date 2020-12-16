<?php

namespace Keboola\Juicer\Pagination;

use Keboola\Juicer\Client\RestClient;
use Keboola\Juicer\Config\JobConfig;

/**
 * For extractors with no pagination
 */
class NoScroller implements ScrollerInterface
{
    /**
     * @inheritdoc
     */
    public function getFirstRequest(RestClient $client, JobConfig $jobConfig)
    {
        return $client->createRequest($jobConfig->getConfig());
    }

    /**
     * @inheritdoc
     */
    public function getNextRequest(RestClient $client, JobConfig $jobConfig, $response, $data)
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function reset()
    {
    }

    /**
     * @inheritdoc
     */
    public function getState(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function setState(array $state): void
    {
    }
}
