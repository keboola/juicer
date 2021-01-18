<?php

declare(strict_types=1);

namespace Keboola\Juicer\Client;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use Keboola\Juicer\Exception\UserException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use Keboola\Utils\Exception\JsonDecodeException;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use function Keboola\Utils\isValidDateTimeString;
use function Keboola\Utils\jsonDecode;

class RestClient
{
    protected Client $client;

    protected array $defaultRequestOptions = [];

    private LoggerInterface $logger;

    private array $ignoreErrors;

    private GuzzleRequestFactory $guzzleRequestFactory;

    /**
     * @param LoggerInterface $logger
     * @param array $guzzleConfig GuzzleHttp\Client defaults
     * @param array $retryConfig @see RestClient::createBackoff()
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
        array $guzzleConfig = [],
        array $retryConfig = [],
        array $defaultOptions = [],
        array $ignoreErrors = []
    ) {
        $guzzleConfig['handler'] = $guzzleConfig['handler'] ?? HandlerStack::create();
        $guzzle = new Client($guzzleConfig);
        // TODO retry

        $this->logger = $logger;
        $this->client = $guzzle;
        $this->defaultRequestOptions = $defaultOptions;
        $this->ignoreErrors = $ignoreErrors;
        $this->guzzleRequestFactory = new GuzzleRequestFactory();
    }

    /**
     * Update request config with default options
     */
    protected function getRequestConfig(array $config): array
    {
        if (!empty($this->defaultRequestOptions)) {
            $config = array_replace_recursive($this->defaultRequestOptions, $config);
        }

        return $config;
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    private function handleException(\Throwable $e, ?ResponseInterface $response): ?\stdClass
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
                    $result = new \stdClass();
                    $result->errorData = (string) ($response->getBody());
                }
            } else {
                $this->logger->warning('Failed to process response ' . $e->getMessage());
                $result = new \stdClass();
                $result->errorData = $e->getMessage();
            }
            return $result;
        } else {
            return null;
        }
    }

    /**
     * @param RestRequest $request
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
            if (!$respObj) {
                /** @var ResponseInterface $response */
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
            } else {
                return $respObj;
            }
        } catch (RequestException $e) {
            $respObj = $this->handleException($e, null);
            if (!$respObj) {
                if ($e->getPrevious() && $e->getPrevious() instanceof UserException) {
                    throw $e->getPrevious();
                } else {
                    throw new UserException($e->getMessage(), $e->getCode(), $e);
                }
            } else {
                return $respObj;
            }
        }
    }

    /**
     * @param ResponseInterface $response
     * @return array|object Should be anything that can result from json_decode
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
     *    'options' => [], // SOAP only
     *    'inputHeader' => '' // SOAP only
     * ]
     */
    public function createRequest(array $config): RestRequest
    {
        return new RestRequest($this->getRequestConfig($config));
    }

    /**
     * Create exponential backoff for GuzzleClient
     *
     * options
     *  - maxRetries: (integer) max retries count
     *  - http
     *      - retryHeader (string) header containing retry time header
     *      - codes (array) list of status codes to retry on
     * - curl
     *      - codes (array) list of error codes to retry on
     *
     */
    private static function createBackoff(array $options, LoggerInterface $logger): RetrySubscriber
    {
        $headerName = isset($options['http']['retryHeader']) ? $options['http']['retryHeader'] : 'Retry-After';
        $httpRetryCodes = isset($options['http']['codes']) ?
            $options['http']['codes'] : [500, 502, 503, 504, 408, 420, 429];
        $maxRetries = isset($options['maxRetries']) ? (int) $options['maxRetries']: 10;

        $curlRetryCodes = isset($options['curl']['codes']) ? $options['curl']['codes'] : [
            CURLE_OPERATION_TIMEOUTED,
            CURLE_COULDNT_RESOLVE_HOST,
            CURLE_COULDNT_CONNECT,
            CURLE_SSL_CONNECT_ERROR,
            CURLE_GOT_NOTHING,
            CURLE_RECV_ERROR,
        ];

        return new RetrySubscriber([
            'filter' => RetrySubscriber::createChainFilter([
                RetrySubscriber::createStatusFilter($httpRetryCodes),
                RetrySubscriber::createCurlFilter($curlRetryCodes),
            ]),
            'max' => $maxRetries,
            'delay' => function (int $retries, AbstractTransferEvent $event) use ($headerName, $logger) {
                $delay = self::getRetryDelay($retries, $event, $headerName);

                $errData = [
                    'http_code' => !empty($event->getTransferInfo()['http_code']) ?
                        $event->getTransferInfo()['http_code'] : null,
                    'body' => is_null($event->getResponse()) ? null : (string) $event->getResponse()->getBody(),
                    'url' =>  !empty($event->getTransferInfo()['url']) ?
                        $event->getTransferInfo()['url'] : $event->getRequest()->getUrl(),
                ];
                if ($event instanceof ErrorEvent) {
                    $errData['message'] = $event->getException()->getMessage();
                }
                $logger->debug("Http request failed, retrying in {$delay}s", $errData);

                // ms > s
                return 1000 * $delay;
            },
        ]);
    }

    protected static function getRetryDelay(int $retries, AbstractTransferEvent $event, string $headerName): int
    {
        if (is_null($event->getResponse())
            || !$event->getResponse()->hasHeader($headerName)
        ) {
            return RetrySubscriber::exponentialDelay($retries, $event);
        }

        $retryAfter = $event->getResponse()->getHeader($headerName);
        if (is_numeric($retryAfter)) {
            $retryAfter = (int) $retryAfter;
            if ($retryAfter < time() - strtotime('1 day', 0)) {
                return $retryAfter;
            } else {
                return $retryAfter - time();
            }
        }

        if (isValidDateTimeString($retryAfter, DATE_RFC1123)) {
            /** @var \DateTime $date */
            $date = \DateTime::createFromFormat(DATE_RFC1123, $retryAfter);
            return $date->getTimestamp() - time();
        }

        return RetrySubscriber::exponentialDelay($retries, $event);
    }
}
