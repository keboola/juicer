<?php

namespace Keboola\Juicer\Pagination;

use Keboola\Juicer\Client\RestClient;
use Keboola\Juicer\Config\JobConfig;
use Keboola\Juicer\Exception\UserException;

/**
 * Scrolls using a parameter within page's response.
 */
class ResponseParamScroller extends AbstractResponseScroller implements ScrollerInterface
{
    protected string $responseParam;

    protected string $queryParam;

    protected array $scrollRequest = [];

    private bool $includeParams = false;

    /**
     * ResponseParamScroller constructor.
     * @param array $config
     *      [
     *          'responseParam' => string // Parameter within the response containing next page info
     *          'queryParam' => string // Query parameter to pass the $responseParam
     *          'includeParams' => bool // Whether to include params from config
     *          'scrollRequest' => array // Override endpoint from config
     *      ]
     * @throws UserException
     */
    public function __construct($config)
    {
        if (empty($config['responseParam'])) {
            throw new UserException("Missing required 'pagination.responseParam' parameter.");
        }
        if (empty($config['queryParam'])) {
            throw new UserException("Missing required 'pagination.queryParam' parameter.");
        }
        if (!empty($config['scrollRequest']) && !is_array($config['scrollRequest'])) {
            throw new UserException("'pagination.scrollRequest' must be a job-like array.");
        }
        $this->responseParam = $config['responseParam'];
        $this->queryParam = $config['queryParam'];
        if (isset($config['includeParams'])) {
            $this->includeParams = (bool)$config['includeParams'];
        }
        if (!empty($config['scrollRequest'])) {
            $this->scrollRequest = $config['scrollRequest'];
        }
    }

    /**
     * @inheritdoc
     */
    public function getNextRequest(RestClient $client, JobConfig $jobConfig, $response, $data)
    {
        $nextParam = \Keboola\Utils\getDataFromPath($this->responseParam, $response, '.');
        if (empty($nextParam)) {
            return false;
        } else {
            $config = $jobConfig->getConfig();

            if (!$this->includeParams) {
                $config['params'] = [];
            }

            if ($this->scrollRequest) {
                $config = $this->createScrollRequest($config, $this->scrollRequest);
            }

            $config['params'][$this->queryParam] = $nextParam;

            return $client->createRequest($config);
        }
    }

    /**
     * Overwrite original endpoint settings with endpoint,
     * params and method from scrollRequest
     *
     * @param array $originalConfig
     * @param array $newConfig
     * @return array
     */
    private function createScrollRequest(array $originalConfig, array $newConfig)
    {
        return array_replace_recursive($originalConfig, $newConfig);
    }
}
