<?php

namespace Keboola\Juicer\Pagination;

use Keboola\Juicer\Client\RestClient;
use Keboola\Juicer\Config\JobConfig;
use GuzzleHttp\Query;

/**
 * Scrolls using URL or Endpoint within page's response.
 */
class ResponseUrlScroller extends AbstractResponseScroller implements ScrollerInterface
{
    /**
     * @var string
     */
    protected $urlParam;

    /**
     * @var bool
     */
    protected $includeParams;

    /**
     * @var bool
     */
    protected $paramIsQuery = false;

    public function __construct($config)
    {
        $this->urlParam = !empty($config['urlKey']) ? $config['urlKey'] : 'next_page';
        $this->includeParams = !empty($config['includeParams']) ? (bool) $config['includeParams'] : false;
        $this->paramIsQuery = !empty($config['paramIsQuery']) ? (bool) $config['paramIsQuery'] : false;
    }

    public static function create(array $config)
    {
        return new self($config);
    }

    /**
     * {@inheritdoc}
     */
    public function getNextRequest(RestClient $client, JobConfig $jobConfig, $response, $data)
    {
        $nextUrl = \Keboola\Utils\getDataFromPath($this->urlParam, $response, '.');

        if (empty($nextUrl)) {
            return false;
        } else {
            $config = $jobConfig->getConfig();

            if (!$this->includeParams) {
                $config['params'] = [];
            }

            if (!$this->paramIsQuery) {
                $config['endpoint'] = $nextUrl;
            } else {
                // Create an array from the query string
                $responseQuery = Query::fromString(ltrim($nextUrl, '?'))->toArray();
                $config['params'] = array_replace($config['params'], $responseQuery);
            }

            return $client->createRequest($config);
        }
    }
}
