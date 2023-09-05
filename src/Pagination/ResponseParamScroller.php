<?php

declare(strict_types=1);

namespace Keboola\Juicer\Pagination;

use Keboola\Juicer\Client\RestClient;
use Keboola\Juicer\Client\RestRequest;
use Keboola\Juicer\Config\JobConfig;
use Keboola\Juicer\Exception\UserException;
use Psr\Log\LoggerInterface;
use function Keboola\Utils\getDataFromPath;

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
    public function __construct(array $config, LoggerInterface $logger)
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
            $this->includeParams = (bool) $config['includeParams'];
        }
        if (!empty($config['scrollRequest'])) {
            $this->scrollRequest = $config['scrollRequest'];
        }

        parent::__construct($logger);
    }

    /**
     * @inheritdoc
     */
    public function getNextRequest(RestClient $client, JobConfig $jobConfig, $response, array $data): ?RestRequest
    {
        $nextParam = getDataFromPath($this->responseParam, $response, '.');
        if (empty($nextParam)) {
            $this->logger->info('No more pages found, stopping pagination.');
            return null;
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
    private function createScrollRequest(array $originalConfig, array $newConfig): array
    {
        return array_replace_recursive($originalConfig, $newConfig);
    }
}
