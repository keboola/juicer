<?php

declare(strict_types=1);

namespace Keboola\Juicer\Pagination;

use Keboola\Juicer\Client\RestClient;
use Keboola\Juicer\Client\RestRequest;
use Keboola\Juicer\Config\JobConfig;
use Psr\Log\LoggerInterface;

/**
 * Scrolls using simple "limit" and "page" query parameters.
 *
 * Limit can be overridden in job's config's query parameters
 * and it will be used instead of extractor's default.
 * Pagination will stop if an empty response is received,
 * or when $limit is set and
 */
class PageScroller extends AbstractScroller implements ScrollerInterface
{
    protected ?int $limit = null;

    protected string $limitParam = 'limit';

    protected string $pageParam = 'page';

    protected int $firstPage = 1;

    protected bool $firstPageParams = true;

    protected int $page;

    /**
     * PageScroller constructor.
     * @param array $config
     *      [
     *          'pageParam' => string // the page parameter
     *          'limit' => int // page size limit
     *          'limitParam' => string // the limit parameter (usually 'limit', 'count', ...)
     *          'firstPage' => int // number of the first page
     *          'firstPageParams` => bool // whether to include the limit and offset in the first request (def. true)
     *      ]
     */
    public function __construct(array $config, LoggerInterface $logger)
    {
        if (!empty($config['pageParam'])) {
            $this->pageParam = (string) $config['pageParam'];
        }
        if (!empty($config['limit'])) {
            $this->limit = (int) $config['limit'];
        }
        if (!empty($config['limitParam'])) {
            $this->limitParam = (string) $config['limitParam'];
        }
        if (isset($config['firstPage'])) {
            $this->firstPage = (int) $config['firstPage'];
        }
        if (isset($config['firstPageParams'])) {
            $this->firstPageParams = (bool) $config['firstPageParams'];
        }

        $this->reset();
        parent::__construct($logger);
    }

    /**
     * @inheritdoc
     */
    public function getFirstRequest(RestClient $client, JobConfig $jobConfig): ?RestRequest
    {
        if ($this->firstPageParams) {
            $config = $this->getParams($jobConfig);
        } else {
            $config = $jobConfig->getConfig();
        }

        return $client->createRequest($config);
    }

    /**
     * @inheritdoc
     */
    public function getNextRequest(RestClient $client, JobConfig $jobConfig, $response, array $data): ?RestRequest
    {
        if (is_null($this->getLimit($jobConfig)) && empty($data)) {
            $this->reset();
            $this->logger->info('Pagination stopped, response is empty.');
            return null;
        } elseif (count($data) < $this->getLimit($jobConfig)) {
            $this->reset();
            $this->logger->info('Pagination stopped, response is smaller than limit.');
            return null;
        } else {
            $this->page++;

            return $client->createRequest($this->getParams($jobConfig));
        }
    }

    /**
     * @inheritdoc
     */
    public function reset(): void
    {
        $this->page = $this->firstPage;
    }

    /**
     * Returns a config with scroller params
     */
    private function getParams(JobConfig $jobConfig): array
    {
        $params = [$this->pageParam => $this->page];
        if (!empty($this->limitParam) && !is_null($this->getLimit($jobConfig))) {
            $params[$this->limitParam] = $this->getLimit($jobConfig);
        }

        $config = $jobConfig->getConfig();
        $config['params'] = array_replace(
            $jobConfig->getParams(),
            $params,
        );
        return $config;
    }

    private function getLimit(JobConfig $jobConfig): ?int
    {
        $params = $jobConfig->getParams();
        return empty($params[$this->limitParam]) ? $this->limit : (int) $params[$this->limitParam];
    }
}
