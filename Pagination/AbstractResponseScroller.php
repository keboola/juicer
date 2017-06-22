<?php

namespace Keboola\Juicer\Pagination;

use Keboola\Juicer\Client\RestClient;
use Keboola\Juicer\Config\JobConfig;

/**
 * Scrolls using URL or Endpoint within page's response.
 */
abstract class AbstractResponseScroller extends AbstractScroller
{
    /**
     * {@inheritdoc}
     */
    public function getFirstRequest(RestClient $client, JobConfig $jobConfig)
    {
        return $client->createRequest($jobConfig->getConfig());
    }

    public function reset()
    {
    }
}
