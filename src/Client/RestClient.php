<?php

declare(strict_types=1);

namespace Keboola\Juicer\Client;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Utils;
use Keboola\Juicer\Exception\UserException;
use Keboola\Juicer\Retry\RetryMiddlewareFactory;
use Keboola\Utils\Exception\JsonDecodeException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerInterface;
use stdClass;
use Throwable;
use UnexpectedValueException;
use function Keboola\Utils\jsonDecode;

class RestClient
{
    protected HandlerStack $handlerStack;

    protected Client $client;

    protected array $defaultRequestOptions = [];

    private LoggerInterface $logger;

    private UriInterface $baseUri;

    private array $ignoreErrors;

    private GuzzleRequestFactory $guzzleRequestFactory;

    /**
     * @param array  $guzzleConfig GuzzleHttp\Client defaults
     * @param array  $retryConfig @see RestClient::createBackoff()
     *
     * retryConfig options
     *  - maxRetries: (integer) max retries count
     *  - http
     *      - retryHeader (string) header containing retry time header
     *      - codes (array) list of status codes to retry on
     * - curl
     *      - codes (array) list of error codes to retry on
     *
     * @param array $defaultOptions
     * @param array $ignoreErrors List of HTTP Status codes which are to be ignored
     */
    public function __construct(
        LoggerInterface $logger,
        string $baseUri,
        array $guzzleConfig = [],
        array $retryConfig = [],
        array $defaultOptions = [],
        array $ignoreErrors = []
    ) {
        // Add base url to guzzle config
        $this->baseUri = Utils::uriFor($baseUri);
        $guzzleConfig['base_uri'] = $this->baseUri;

        // Get/create handler stack
        $guzzleConfig['handler'] = $guzzleConfig['handler'] ?? HandlerStack::create();
        if (!$guzzleConfig['handler'] instanceof HandlerStack) {
            throw new UnexpectedValueException('Type of the "handler" must be HandlerStack.');
        }
        $this->handlerStack = $guzzleConfig['handler'];

        // Create retry middleware
        $retryMiddlewareFactory = new RetryMiddlewareFactory($logger, $retryConfig);
        $this->handlerStack->push($retryMiddlewareFactory->create(), 'retry');

        // Set default HTTP timeouts, 30 seconds for connect / 5 minutes for request
        // https://docs.guzzlephp.org/en/stable/request-options.html#connect-timeout
        // https://docs.guzzlephp.org/en/stable/request-options.html#timeout
        $guzzleConfig['connect_timeout'] = $guzzleConfig['connect_timeout'] ?? 30;
        $guzzleConfig['timeout'] = $guzzleConfig['connect_timeout'] ?? 300;

        // Create Guzzle client
        $guzzle = new Client($guzzleConfig);

        // Use host from guzzle config if present (in previous versions of Guzzle, this was done automatically)
        $defaultHostHeader = null;
        foreach ($guzzleConfig['headers'] ?? [] as $name => $value) {
            if (strtolower((string) $name) === 'host') {
                $defaultHostHeader = $value;
                break;
            }
        }

        $this->logger = $logger;
        $this->client = $guzzle;
        $this->defaultRequestOptions = $defaultOptions;
        $this->ignoreErrors = $ignoreErrors;
        $this->guzzleRequestFactory = new GuzzleRequestFactory($defaultHostHeader);
    }

    public function getBaseUri(): UriInterface
    {
        return $this->baseUri;
    }

    public function getHandlerStack(): HandlerStack
    {
        return $this->handlerStack;
    }

    public function getGuzzleRequestFactory(): GuzzleRequestFactory
    {
        return $this->guzzleRequestFactory;
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    /**
     * @return mixed Raw response as it comes from the client
     * @throws UserException
     * @throws \Exception
     */
    public function download(RestRequest $request)
    {
        try {
            $response = $this->client->send($this->guzzleRequestFactory->create($request));
            try {
                return $this->getObjectFromResponse($response);
            } catch (UserException $e) {
                $respObj = $this->handleException($e, $response);
                if (!$respObj) {
                    throw $e;
                } else {
                    return $respObj;
                }
            }
        } catch (BadResponseException $e) {
            $respObj = $this->handleException($e, $e->getResponse());
            if ($respObj) {
                return $respObj;
            }

            $response = $e->getResponse();
            $data = json_decode((string) $response->getBody(), true);
            if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
                $data = (string) $response->getBody();
            }

            throw new UserException(
                'The API request failed: [' . $response->getStatusCode() . '] ' . $e->getMessage(),
                400,
                $e,
                ['body' => $data]
            );
        } catch (RequestException|ConnectException $e) {
            // ConnectException has no response
            if ($e instanceof RequestException) {
                $respObj = $this->handleException($e, $e->getResponse());
                if ($respObj) {
                    return $respObj;
                }
            }

            // Curl exception
            $context = $e->getHandlerContext();
            $errno = $context['errno'] ?? null;
            $error = $context['error'] ?? null;
            if ($errno > 0) {
                throw new UserException(sprintf('CURL error %d: %s', $errno, $error));
            }

            // Other exception
            if ($e->getPrevious() && $e->getPrevious() instanceof UserException) {
                throw $e->getPrevious();
            } else {
                throw new UserException($e->getMessage(), $e->getCode(), $e);
            }
        }
    }

    /**
     * @return array|object|mixed Should be anything that can result from json_decode
     * @throws UserException
     */
    public function getObjectFromResponse(ResponseInterface $response)
    {
        // Sanitize the JSON
        $body = (string) iconv('UTF-8', 'UTF-8//IGNORE', (string) $response->getBody());
        try {
            $decoded = jsonDecode($body, false, 512, 0, true, true);
        } catch (JsonDecodeException $e) {
            throw new UserException(
                'Invalid JSON response from API: ' . $e->getMessage(),
                0,
                null,
                $e->getData()
            );
        }

        return $decoded;
    }

    /**
     * Create a request from a JobConfig->getConfig() array
     * [
     *    'endpoint' => 'resource', // Required
     *    'params' => [
     *        'some' => 'parameter'
     *    ],
     *    'method' => 'GET', // REST only
     *    'method' => 'GET', // REST only
     *    'options' => [], // SOAP only
     *    'inputHeader' => '' // SOAP only
     * ]
     */
    public function createRequest(array $config, bool $applyDefaults = true): RestRequest
    {
        $config = $applyDefaults ? $this->applyDefaultReuestOptions($config) : $config;
        return new RestRequest($config);
    }

    /**
     * Update request config with default options
     */
    protected function applyDefaultReuestOptions(array $config): array
    {
        if (!empty($this->defaultRequestOptions)) {
            $config = array_replace_recursive($this->defaultRequestOptions, $config);
        }

        return $config;
    }

    protected function handleException(Throwable $e, ?ResponseInterface $response): ?stdClass
    {
        if (($response && in_array($response->getStatusCode(), $this->ignoreErrors)) ||
            in_array($e->getCode(), $this->ignoreErrors)
        ) {
            if ($response) {
                $this->logger->warning('Failed to get response ' . $e->getMessage());
                try {
                    /** @var \stdClass $result */
                    $result = $this->getObjectFromResponse($response);
                } catch (UserException $ex) {
                    $this->logger->warning('Failed to parse response ' . $ex->getMessage());
                    $result = new stdClass();
                    $result->errorData = (string) ($response->getBody());
                }
            } else {
                $this->logger->warning('Failed to process response ' . $e->getMessage());
                $result = new stdClass();
                $result->errorData = $e->getMessage();
            }
            return $result;
        } else {
            return null;
        }
    }
}
