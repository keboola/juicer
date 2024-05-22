<?php

declare(strict_types=1);

namespace Keboola\Juicer\Pagination;

use Keboola\Juicer\Client\RestClient;
use Keboola\Juicer\Client\RestRequest;
use Keboola\Juicer\Config\JobConfig;

/**
 * Scrolls using URL or Endpoint within page's response.
 */
abstract class AbstractResponseScroller extends AbstractScroller
{
    /**
     * @inheritdoc
     */
    public function getFirstRequest(RestClient $client, JobConfig $jobConfig): ?RestRequest
    {
        return $client->createRequest($jobConfig->getConfig());
    }

    protected function getFollowupRequest(RestClient $client, array $config): ?RestRequest
    {
        if (!empty($config['followupRequest'])) {
            $config['method'] = $config['followupRequest'];
        }
        return $client->createRequest($config);
    }

    /**
     * @inheritdoc
     */
    public function reset(): void
    {
    }
}
