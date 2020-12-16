<?php

namespace Keboola\Juicer\Pagination;

use Keboola\Juicer\Client\RestClient;
use Keboola\Juicer\Config\JobConfig;

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
     *          'firstPageParams` => bool // whether to include the limit and offset in the first request (default = true)
     *      ]
     */
    public function __construct(array $config)
    {
        if (!empty($config['pageParam'])) {
            $this->pageParam = $config['pageParam'];
        }
        if (!empty($config['limit'])) {
            $this->limit = $config['limit'];
        }
        if (!empty($config['limitParam'])) {
            $this->limitParam = $config['limitParam'];
        }
        if (isset($config['firstPage'])) {
            $this->firstPage = $config['firstPage'];
        }
        if (isset($config['firstPageParams'])) {
            $this->firstPageParams = $config['firstPageParams'];
        }

        $this->reset();
    }

    /**
     * @inheritdoc
     */
    public function getFirstRequest(RestClient $client, JobConfig $jobConfig)
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
    public function getNextRequest(RestClient $client, JobConfig $jobConfig, $response, $data)
    {
        if ((is_null($this->getLimit($jobConfig)) && empty($data))
            || (count($data) < $this->getLimit($jobConfig))
        ) {
            $this->reset();
            return false;
        } else {
            $this->page++;

            return $client->createRequest($this->getParams($jobConfig));
        }
    }

    /**
     * @inheritdoc
     */
    public function reset()
    {
        $this->page = $this->firstPage;
    }

    /**
     * Returns a config with scroller params
     * @param JobConfig $jobConfig
     * @return array
     */
    private function getParams(JobConfig $jobConfig)
    {
        $params = [$this->pageParam => $this->page];
        if (!empty($this->limitParam) && !is_null($this->getLimit($jobConfig))) {
            $params[$this->limitParam] = $this->getLimit($jobConfig);
        }

        $config = $jobConfig->getConfig();
        $config['params'] = array_replace(
            $jobConfig->getParams(),
            $params
        );
        return $config;
    }

    /**
     * @param JobConfig $jobConfig
     * @return int|null
     */
    private function getLimit(JobConfig $jobConfig)
    {
        $params = $jobConfig->getParams();
        return empty($params[$this->limitParam]) ? $this->limit : $params[$this->limitParam];
    }
}
