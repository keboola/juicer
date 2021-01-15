<?php

declare(strict_types=1);

namespace Keboola\Juicer\Pagination;

use Keboola\Juicer\Client\RestClient;
use Keboola\Juicer\Client\RestRequest;
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
    protected int $limit;

    protected string $limitParam = 'limit';

    protected string $offsetParam = 'offset';

    protected bool $firstPageParams = true;

    protected int $pointer = 0;

    protected bool $offsetFromJob = false;

    /**
     * OffsetScroller constructor.
     * @param array $config
     *      [
     *          'limit' => int // mandatory parameter; size of each page
     *          'limitParam' => string // the limit parameter (usually 'limit', 'count', ...)
     *          'offsetParam' => string // the offset parameter
     *          'firstPageParams' => bool // whether to include the limit and offset in the first request (def. true)
     *          'offsetFromJob' => bool // use offset parameter provided in the job parameters
     *      ]
     * @throws UserException
     */
    public function __construct(array $config)
    {
        if (empty($config['limit'])) {
            throw new UserException("Missing 'pagination.limit' attribute required for offset pagination");
        }

        if (!is_numeric($config['limit'])) {
            throw new UserException(sprintf(
                "Parameter 'pagination.limit' is not numeric. Value '%s'.",
                json_encode($config['limit'])
            ));
        }

        $this->limit = (int) $config['limit'];

        if (!empty($config['limitParam'])) {
            $this->limitParam = (string) $config['limitParam'];
        }
        if (!empty($config['offsetParam'])) {
            $this->offsetParam = (string) $config['offsetParam'];
        }
        if (isset($config['firstPageParams'])) {
            $this->firstPageParams = (bool) $config['firstPageParams'];
        }
        if (isset($config['offsetFromJob'])) {
            $this->offsetFromJob = (bool) $config['offsetFromJob'];
        }
    }

    /**
     * @inheritdoc
     */
    public function getFirstRequest(RestClient $client, JobConfig $jobConfig): ?RestRequest
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
     * @inheritdoc
     */
    public function getNextRequest(RestClient $client, JobConfig $jobConfig, $response, array $data): ?RestRequest
    {
        if (count($data) < $this->getLimit($jobConfig)) {
            $this->reset();
            return null;
        } else {
            $this->pointer += $this->getLimit($jobConfig);

            return $client->createRequest($this->getParams($jobConfig));
        }
    }

    public function reset(): void
    {
        $this->pointer = 0;
    }

    /**
     * Returns a config with scroller params
     */
    private function getParams(JobConfig $jobConfig): array
    {
        $config = $jobConfig->getConfig();
        $scrollParams = [
            $this->limitParam => $this->getLimit($jobConfig),
            $this->offsetParam => $this->pointer,
        ];

        $config['params'] = array_replace($jobConfig->getParams(), $scrollParams);
        return $config;
    }

    private function getLimit(JobConfig $jobConfig): int
    {
        $params = $jobConfig->getParams();
        return empty($params[$this->limitParam]) ? $this->limit : (int) $params[$this->limitParam];
    }
}
