<?php

declare(strict_types=1);

namespace Keboola\Juicer\Pagination;

use DateTime;
use GuzzleHttp\Psr7\Query;
use GuzzleHttp\Psr7\Uri;
use Keboola\Juicer\Client\RestClient;
use Keboola\Juicer\Client\RestRequest;
use Keboola\Juicer\Config\JobConfig;
use Psr\Log\LoggerInterface;
use function Keboola\Utils\getDataFromPath;

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
    public function __construct(array $config, LoggerInterface $logger)
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

        parent::__construct($logger);
    }

    /**
     * @inheritdoc
     */
    public function getNextRequest(RestClient $client, JobConfig $jobConfig, $response, array $data): ?RestRequest
    {
        $nextUrl = getDataFromPath($this->urlParam, $response, '.');

        if (empty($nextUrl)) {
            $this->logger->info('No more pages to scroll.');
            return null;
        }

        // start_time validation
        // https://developer.zendesk.com/rest_api/docs/core/incremental_export#incremental-ticket-export
        $now = new DateTime();
        $startDateTimeStr = Query::parse((new Uri($nextUrl))->getQuery())['start_time'] ?? null;
        $startDateTime = $startDateTimeStr ? DateTime::createFromFormat('U', $startDateTimeStr) : null;

        if ($startDateTime && $startDateTime > $now->modify(sprintf('-%d minutes', self::NEXT_PAGE_FILTER_MINUTES))) {
            $this->logger->info(sprintf(
                'Next page start_time "%s" is too recent, skipping...',
                $startDateTime->format('Y-m-d H:i:s'),
            ));
            return null;
        }

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
