<?php

namespace Keboola\Juicer\Pagination;

use Keboola\Juicer\Client\RestClient;
use Keboola\Juicer\Exception\UserException;
use Keboola\Juicer\Config\JobConfig;

/**
 * Scrolls using simple "limit" and "offset" query parameters.
 * Limit can be overridden in job's config's query parameters
 * and it will be used instead of extractor's default.
 * Offset can be overridden if 'offsetFromJob' is enabled
 */
class OffsetScroller extends AbstractScroller implements ScrollerInterface
{
    const DEFAULT_LIMIT_PARAM = 'limit';
    const DEFAULT_OFFSET_PARAM = 'offset';
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
    protected $offsetParam;
    /**
     * @var bool
     */
    protected $firstPageParams;
    /**
     * @var int
     */
    protected $pointer = 0;
    /**
     * @var bool
     */
    protected $offsetFromJob = false;

    public function __construct(array $config)
    {
        $this->limit = $config['limit'];
        $this->limitParam = !empty($config['limitParam']) ? $config['limitParam'] : self::DEFAULT_LIMIT_PARAM;
        $this->offsetParam = !empty($config['offsetParam']) ? $config['offsetParam'] : self::DEFAULT_OFFSET_PARAM;
        $this->firstPageParams = isset($config['firstPageParams']) ? $config['firstPageParams'] : self::FIRST_PAGE_PARAMS;
        if (!empty($config['offsetFromJob'])) {
            $this->offsetFromJob = true;
        }
    }

    /**
     * @param array $config
     *     [
     *        'limit' => int // mandatory parameter; size of each page
     *         'limitParam' => string // the limit parameter (usually 'limit', 'count', ...)
     *         'offsetParam' => string // the offset parameter
     *         'firstPageParams' => bool // whether to include the limit and offset in the first request (default = true)
     *     ]
     * @return static
     * @throws UserException
     */
    public static function create(array $config)
    {
        if (empty($config['limit'])) {
            throw new UserException("Missing 'pagination.limit' attribute required for offset pagination");
        }

        return new self($config);
    }

    /**
     * {@inheritdoc}
     */
    public function getFirstRequest(RestClient $client, JobConfig $jobConfig)
    {
        if ($this->offsetFromJob && !empty($jobConfig->getParams()[$this->offsetParam])) {
            $this->pointer = $jobConfig->getParams()[$this->offsetParam];
        }

        if ($this->firstPageParams) {
            $config = $this->getParams($jobConfig);
        } else {
            $config = $jobConfig->getConfig();
        }

        return $client->createRequest($config);
    }

    /**
     * {@inheritdoc}
     * @todo increase by count($data) instead of limit? Could make limit optional then
     */
    public function getNextRequest(RestClient $client, JobConfig $jobConfig, $response, $data)
    {
        if (count($data) < $this->getLimit($jobConfig)) {
            $this->reset();
            return false;
        } else {
            $this->pointer += $this->getLimit($jobConfig);

            return $client->createRequest($this->getParams($jobConfig));
        }
    }

    public function reset()
    {
        $this->pointer = 0;
    }

    /**
     * Returns a config with scroller params
     * @param JobConfig $jobConfig
     * @return array
     */
    protected function getParams(JobConfig $jobConfig)
    {
        $config = $jobConfig->getConfig();
        $scrollParams = [
            $this->limitParam => $this->getLimit($jobConfig),
            $this->offsetParam => $this->pointer
        ];

        $config['params'] = array_replace($jobConfig->getParams(), $scrollParams);
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
