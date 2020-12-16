<?php

declare(strict_types=1);

namespace Keboola\Juicer\Pagination;

use GuzzleHttp\Url;
use Keboola\Juicer\Client\RestClient;
use Keboola\Juicer\Config\JobConfig;
use GuzzleHttp\Query;

class ZendeskResponseUrlScroller extends AbstractResponseScroller implements ScrollerInterface
{
    public const NEXT_PAGE_FILTER_MINUTES = 6;

    protected string $urlParam = 'next_page';

    protected bool $includeParams = false;

    protected bool $paramIsQuery = false;

    /**
     * ZendeskResponseUrlScroller constructor.
     * @param array $config
     *      [
     *          'urlKey' => string // Key in the JSON response containing the URL
     *          'includeParams' => bool // Whether to include params from config
     *          'paramIsQuery' => bool // Pick parameters from the scroll URL and use them with job configuration
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
    }

    /**
     * @inheritdoc
     */
    public function getNextRequest(RestClient $client, JobConfig $jobConfig, $response, $data)
    {
        $nextUrl = \Keboola\Utils\getDataFromPath($this->urlParam, $response, '.');

        if (empty($nextUrl)) {
            return false;
        }

        // start_time validation
        // https://developer.zendesk.com/rest_api/docs/core/incremental_export#incremental-ticket-export
        $now = new \DateTime();
        $startDateTimeStr = Url::fromString($nextUrl)->getQuery()->get('start_time');
        $startDateTime = $startDateTimeStr ? \DateTime::createFromFormat('U', $startDateTimeStr) : null;

        if ($startDateTime && $startDateTime > $now->modify(sprintf('-%d minutes', self::NEXT_PAGE_FILTER_MINUTES))) {
            return false;
        }

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
