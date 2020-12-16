<?php

declare(strict_types=1);

namespace Keboola\Juicer\Pagination;

use Keboola\Juicer\Client\RestClient;
use Keboola\Juicer\Client\RestRequest;
use Keboola\Juicer\Config\JobConfig;

interface ScrollerInterface
{
    /**
     * @param RestClient $client
     * @param JobConfig $jobConfig
     * @return RestRequest|false
     */
    public function getFirstRequest(RestClient $client, JobConfig $jobConfig);

    /**
     * @param RestClient $client
     * @param JobConfig $jobConfig
     * @param array|object $response
     * @param array $data
     * @return RestRequest|false
     */
    public function getNextRequest(RestClient $client, JobConfig $jobConfig, $response, $data);

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
