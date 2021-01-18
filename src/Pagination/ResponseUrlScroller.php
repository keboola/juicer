<?php

declare(strict_types=1);

namespace Keboola\Juicer\Pagination;

use GuzzleHttp\Psr7\Query;
use Keboola\Juicer\Client\RestClient;
use Keboola\Juicer\Client\RestRequest;
use Keboola\Juicer\Config\JobConfig;

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
            $this->includeParams = (bool) $config['includeParams'];
        }
        if (isset($config['paramIsQuery'])) {
            $this->paramIsQuery = (bool) $config['paramIsQuery'];
        }
        if (isset($config['delimiter'])) {
            $this->delimiter = $config['delimiter'];
        }
    }

    /**
     * @inheritdoc
     */
    public function getNextRequest(RestClient $client, JobConfig $jobConfig, $response, array $data): ?RestRequest
    {
        $nextUrl = \Keboola\Utils\getDataFromPath($this->urlParam, $response, $this->delimiter);

        if (empty($nextUrl)) {
            return null;
        } else {
            $config = $jobConfig->getConfig();

            if (!$this->includeParams) {
                $config['params'] = [];
            }

            if (!$this->paramIsQuery) {
                $config['endpoint'] = $nextUrl;
            } else {
                // Create an array from the query string
                $responseQuery = Query::parse(ltrim($nextUrl, '?'));
                $config['params'] = array_replace($config['params'], $responseQuery);
            }

            return $client->createRequest($config);
        }
    }
}
