<?php

declare(strict_types=1);

namespace Keboola\Juicer\Pagination;

use Keboola\Juicer\Client\RestClient;
use Keboola\Juicer\Client\RestRequest;
use Keboola\Juicer\Config\JobConfig;

interface ScrollerInterface
{
    public function getFirstRequest(RestClient $client, JobConfig $jobConfig): ?RestRequest;

    public function getNextRequest(
        RestClient $client,
        JobConfig $jobConfig,
        array|object $response,
        array $data,
    ): ?RestRequest;

    /**
     * Reset the pagination pointer
     */
    public function reset(): void;

    /**
     * Get the current scrolling state
     */
    public function getState(): array;

    /**
     * Restore the scroller state
     */
    public function setState(array $state): void;
}
