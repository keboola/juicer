<?php

namespace Keboola\Juicer\Pagination;

use Keboola\Juicer\Client\ClientInterface,
    Keboola\Juicer\Config\JobConfig,
    Keboola\Juicer\Exception\UserException;

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
     * @param string $responseParam Parameter within the response
     *  containing next page info
     * @param string $queryParam Query parameter to pass the $responseParam
     * @param bool $includeParams Whether to include params from config
     * @param array $scrollRequest Override endpoint from config?
     */
    public function __construct(
        $responseParam,
        $queryParam,
        $includeParams = false,
        array $scrollRequest = null
    ) {
        $this->responseParam = $responseParam;
        $this->queryParam = $queryParam;
        $this->includeParams = $includeParams;
        $this->scrollRequest = $scrollRequest;
    }

    public static function create(array $config)
    {
        if (empty($config['responseParam'])) {
            throw new UserException("Missing required 'pagination.responseParam' parameter.");
        }
        if (empty($config['queryParam'])) {
            throw new UserException("Missing required 'pagination.queryParam' parameter.");
        }

        return new self(
            $config['responseParam'],
            $config['queryParam'],
            !empty($config['includeParams']) ? (bool) $config['includeParams'] : false,
            !empty($config['scrollRequest']) ? $config['scrollRequest'] : null
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getNextRequest(ClientInterface $client, JobConfig $jobConfig, $response, $data)
    {
        if (empty($response->{$this->responseParam})) {
            return false;
        } else {
            $config = $jobConfig->getConfig();

            if (!$this->includeParams) {
                $config['params'] = [];
            }

            if (!is_null($this->scrollRequest)) {
                $config = $this->createScrollRequest($config, $this->scrollRequest);
            }

            $config['params'][$this->queryParam] = $response->{$this->responseParam};

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