<?php

namespace Keboola\Juicer\Pagination;

use Keboola\Juicer\Client\ClientInterface,
    Keboola\Juicer\Config\JobConfig;

/**
 * Scrolls using simple "limit" and "page" query parameters.
 *
 * Limit can be overriden in job's config's query parameters
 * and it will be used instead of extractor's default.
 * Pagination will stop if an empty response is received,
 * or when $limit is set and
 */
class PageScroller extends AbstractScroller implements ScrollerInterface
{
    const DEFAULT_PAGE_PARAM = 'page';
    const DEFAULT_LIMIT = null;
    const DEFAULT_LIMIT_PARAM = 'limit';
    const DEFAULT_FIRST_PAGE = 1;
    const FIRST_PAGE_PARAMS = true;

    /**
     * @var int
     */
    protected $limit;
    /**
     * @var string
     */
    protected $limitParam;
    /**
     * @var string
     */
    protected $pageParam;
    /**
     * @var int
     */
    protected $firstPage;
    /**
     * @var bool
     */
    protected $firstPageParams;
    /**
     * @var int
     */
    protected $page;

    public function __construct($config) {
        $this->pageParam = !empty($config['pageParam']) ? $config['pageParam'] : self::DEFAULT_PAGE_PARAM;
        $this->limit = !empty($config['limit']) ? $config['limit'] : self::DEFAULT_LIMIT;
        $this->limitParam = !empty($config['limitParam']) ? $config['limitParam'] : self::DEFAULT_LIMIT_PARAM;
        $this->firstPage = isset($config['firstPage']) ? $config['firstPage'] : self::DEFAULT_FIRST_PAGE;
        $this->firstPageParams = isset($config['firstPageParams']) ? $config['firstPageParams'] : self::FIRST_PAGE_PARAMS;

        $this->reset();
    }

    public static function create(array $config)
    {
        return new self($config);
    }

    /**
     * {@inheritdoc}
     */
    public function getFirstRequest(ClientInterface $client, JobConfig $jobConfig)
    {
        if ($this->firstPageParams) {
            $config = $this->getParams($jobConfig);
        } else {
            $config = $jobConfig->getConfig();
        }

        return $client->createRequest($config);
    }

    /**
     * {@inheritdoc}
     */
    public function getNextRequest(ClientInterface $client, JobConfig $jobConfig, $response, $data)
    {
        if (
            (is_null($this->getLimit($jobConfig)) && empty($data))
            || (count($data) < $this->getLimit($jobConfig))
        ) {
            $this->reset();
            return false;
        } else {
            $this->page++;

            return $client->createRequest($this->getParams($jobConfig));
        }
    }

    public function reset()
    {
        $this->page = $this->firstPage;
    }

    /**
     * Returns a config with scroller params
     * @param JobConfig $jobConfig
     * @return array
     */
    protected function getParams(JobConfig $jobConfig)
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
     * @return int
     */
    protected function getLimit(JobConfig $jobConfig)
    {
        $params = $jobConfig->getParams();
        return empty($params[$this->limitParam]) ? $this->limit : $params[$this->limitParam];
    }
}
