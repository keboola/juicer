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
    protected string $urlParam = 'next_page';

    protected bool $includeParams = false;

    protected bool $paramIsQuery = false;
    

    protected string $delimiter = '.';

    /**
     * ResponseUrlScroller constructor.
     * @param array $config
     *      [
     *          'urlKey' => string // Key in the JSON response containing the URL
     *          'includeParams' => bool // Whether to include params from config
     *          'paramIsQuery' => bool // Pick parameters from the scroll URL and use them with job configuration
     *          'delimiter' => string // Data path separator char
     *      ]
     */
    public function __construct(array $config)
    {
        if (!empty($config['urlKey'])) {
            $this->urlParam = $config['urlKey'];
        }
        if (isset($config['includeParams'])) {
            $this->includeParams = (bool)$config['includeParams'];
        }
        if (isset($config['paramIsQuery'])) {
            $this->paramIsQuery = (bool)$config['paramIsQuery'];
        }
        if (isset($config['delimiter'])) {
            $this->delimiter = $config['delimiter'];
        }
    }

    /**
     * @inheritdoc
     */
    public function getNextRequest(RestClient $client, JobConfig $jobConfig, $response, $data)
    {
        $nextUrl = \Keboola\Utils\getDataFromPath($this->urlParam, $response, $this->delimiter);

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
