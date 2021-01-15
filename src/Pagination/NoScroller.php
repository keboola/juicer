<?php

declare(strict_types=1);

namespace Keboola\Juicer\Pagination;

use Keboola\Juicer\Client\RestClient;
use Keboola\Juicer\Client\RestRequest;
use Keboola\Juicer\Config\JobConfig;

/**
 * For extractors with no pagination
 */
class NoScroller implements ScrollerInterface
{
    /**
     * @inheritdoc
     */
    public function getFirstRequest(RestClient $client, JobConfig $jobConfig): ?RestRequest
    {
        return $client->createRequest($jobConfig->getConfig());
    }

    /**
     * @inheritdoc
     */
    public function getNextRequest(RestClient $client, JobConfig $jobConfig, $response, array $data): ?RestRequest
    {
        return null;
    }

    /**
     * @inheritdoc
     */
    public function reset(): void
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
