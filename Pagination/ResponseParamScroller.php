<?php

namespace Keboola\Juicer\Pagination;

use Keboola\Juicer\Client\ClientInterface;
use Keboola\Juicer\Config\JobConfig;
use Keboola\Juicer\Exception\UserException;
use Keboola\Utils\Utils;

/**
 * Scrolls using a parameter within page's response.
 *
 * @todo scollRequest could be in endpoint's configuration
 */
class ResponseParamScroller extends AbstractResponseScroller implements ScrollerInterface
{
    /**
     * @var string
     */
    protected $responseParam;

    /**
     * @var string
     */
    protected $queryParam;

    /**
     * @var array
     */
    protected $scrollRequest;

    /**
     * @var bool
     */
    protected $includeParams;

    /**
     * @param $config
     * @internal param string $responseParam Parameter within the response
     *  containing next page info
     * @internal param string $queryParam Query parameter to pass the $responseParam
     * @internal param bool $includeParams Whether to include params from config
     * @internal param array $scrollRequest Override endpoint from config?
     */
    public function __construct($config)
    {
        $this->responseParam = $config['responseParam'];
        $this->queryParam = $config['queryParam'];
        $this->includeParams = !empty($config['includeParams']) ? (bool) $config['includeParams'] : false;
        $this->scrollRequest = !empty($config['scrollRequest']) ? $config['scrollRequest'] : null;
    }

    public static function create(array $config)
    {
        if (empty($config['responseParam'])) {
            throw new UserException("Missing required 'pagination.responseParam' parameter.");
        }
        if (empty($config['queryParam'])) {
            throw new UserException("Missing required 'pagination.queryParam' parameter.");
        }

        return new self($config);
    }

    /**
     * {@inheritdoc}
     */
    public function getNextRequest(ClientInterface $client, JobConfig $jobConfig, $response, $data)
    {
        $nextParam = Utils::getDataFromPath($this->responseParam, $response, '.');
        if (empty($nextParam)) {
            return false;
        } else {
            $config = $jobConfig->getConfig();

            if (!$this->includeParams) {
                $config['params'] = [];
            }

            if (!is_null($this->scrollRequest)) {
                $config = $this->createScrollRequest($config, $this->scrollRequest);
            }

            $config['params'][$this->queryParam] = $nextParam;

            return $client->createRequest($config);
        }
    }

    /**
     * Overwrite oriiginal endpoint settings with endpoint,
     * params and method from scrollRequest
     *
     * @param array $originalConfig
     * @param array $newConfig
     * @return array
     */
    protected function createScrollRequest(array $originalConfig, array $newConfig)
    {
        return array_replace_recursive($originalConfig, $newConfig);
    }
}
