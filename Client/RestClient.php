<?php

namespace Keboola\Juicer\Client;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Message\ResponseInterface;
use Keboola\Juicer\Exception\UserException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Message\Request as GuzzleRequest;
use GuzzleHttp\Subscriber\Retry\RetrySubscriber;
use GuzzleHttp\Event\AbstractTransferEvent;
use GuzzleHttp\Event\ErrorEvent;
use Keboola\Utils\Exception\JsonDecodeException;
use Psr\Log\LoggerInterface;

class RestClient
{
    /**
     * @var Client
     */
    protected $client;

    /**
     * @var array
     */
    protected $defaultRequestOptions = [];

    /**
     * @var LoggerInterface
     */
    private $logger;


    /**
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
     * @param LoggerInterface $logger
     * @return RestClient
     */
    public function __construct(LoggerInterface $logger, $guzzleConfig = [], $retryConfig = [], $defaultOptions = [])
    {
        $guzzle = new Client($guzzleConfig);
        $guzzle->getEmitter()->attach(self::createBackoff($retryConfig, $logger));

        $guzzle->getEmitter()->on('error', function (ErrorEvent $errorEvent) {
            $errno = $errorEvent->getTransferInfo('errno');
            $error = $errorEvent->getTransferInfo('error');
            if ($errno > 0) {
                throw new UserException(sprintf("CURL error %d: %s", $errno, $error));
            }
        }, "last");
        $this->logger = $logger;
        $this->client = $guzzle;
        $this->defaultRequestOptions = $defaultOptions;
    }

    /**
     * Update request config with default options
     * @param array $config
     * @return array
     */
    protected function getRequestConfig(array $config) : array
    {
        if (!empty($this->defaultRequestOptions)) {
            $config = array_replace_recursive($this->defaultRequestOptions, $config);
        }

        return $config;
    }

    /**
     * @return Client
     */
    public function getClient() : Client
    {
        return $this->client;
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
            $response = $this->client->send($this->getGuzzleRequest($request));
            return $this->getObjectFromResponse($response);
        } catch (BadResponseException $e) {
            $data = json_decode($e->getResponse()->getBody(), true);
            if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
                $data = (string) $e->getResponse()->getBody();
            }

            throw new UserException(
                "The API request failed: [" . $e->getResponse()->getStatusCode() . "] " . $e->getMessage(),
                400,
                $e,
                ['body' => $data]
            );
        } catch (RequestException $e) {
            if ($e->getPrevious() && $e->getPrevious() instanceof UserException) {
                throw $e->getPrevious();
            } else {
                throw $e;
            }
        }
    }

    /**
     * @param ResponseInterface $response
     * @return array|object Should be anything that can result from json_decode
     * @throws UserException
     */
    protected function getObjectFromResponse(ResponseInterface $response)
    {
        // Sanitize the JSON
        $body = iconv('UTF-8', 'UTF-8//IGNORE', $response->getBody());
        try {
            $decoded = \Keboola\Utils\jsonDecode($body, false, 512, 0, true, true);
        } catch (JsonDecodeException $e) {
            throw new UserException(
                "Invalid JSON response from API: " . $e->getMessage(),
                0,
                null,
                $e->getData()
            );
        }

        return $decoded;
    }

    /**
     * @param RestRequest $request
     * @return GuzzleRequest
     * @throws UserException
     */
    protected function getGuzzleRequest(RestRequest $request)
    {
        switch ($request->getMethod()) {
            case 'GET':
                $method = $request->getMethod();
                $endpoint = \Keboola\Utils\buildUrl($request->getEndpoint(), $request->getParams());
                $options = [];
                break;
            case 'POST':
                $method = $request->getMethod();
                $endpoint = $request->getEndpoint();
                $options = ['json' => $request->getParams()];
                break;
            case 'FORM':
                $method = 'POST';
                $endpoint = $request->getEndpoint();
                $options = ['body' => $request->getParams()];
                break;
            default:
                throw new UserException("Unknown request method '" . $request->getMethod() . "' for '" . $request->getEndpoint() . "'");
                break;
        }

        if (!empty($request->getHeaders())) {
            $options['headers'] = $request->getHeaders();
        }

        return $this->client->createRequest($method, $endpoint, $options);
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
     * @param array $config
     * @return RestRequest
     */
    public function createRequest(array $config)
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
     * @param array $options
     * @param LoggerInterface $logger
     * @return RetrySubscriber
     */
    private static function createBackoff(array $options, LoggerInterface $logger)
    {
        $headerName = isset($options['http']['retryHeader']) ? $options['http']['retryHeader'] : 'Retry-After';
        $httpRetryCodes = isset($options['http']['codes']) ? $options['http']['codes'] : [500, 502, 503, 504, 408, 420, 429];
        $maxRetries = isset($options['maxRetries']) ? (int) $options['maxRetries']: 10;

        $curlRetryCodes = isset($options['curl']['codes']) ? $options['curl']['codes'] : [
            CURLE_OPERATION_TIMEOUTED,
            CURLE_COULDNT_RESOLVE_HOST,
            CURLE_COULDNT_CONNECT,
            CURLE_SSL_CONNECT_ERROR,
            CURLE_GOT_NOTHING,
            CURLE_RECV_ERROR
        ];

        return new RetrySubscriber([
            'filter' => RetrySubscriber::createChainFilter([
                RetrySubscriber::createStatusFilter($httpRetryCodes),
                RetrySubscriber::createCurlFilter($curlRetryCodes)
            ]),
            'max' => $maxRetries,
            'delay' => function ($retries, AbstractTransferEvent $event) use ($headerName, $logger) {
                $delay = self::getRetryDelay($retries, $event, $headerName);

                $errData = [
                    "http_code" => !empty($event->getTransferInfo()['http_code']) ? $event->getTransferInfo()['http_code'] : null,
                    "body" => is_null($event->getResponse()) ? null : (string) $event->getResponse()->getBody(),
                    "url" =>  !empty($event->getTransferInfo()['url']) ? $event->getTransferInfo()['url'] : $event->getRequest()->getUrl(),
                ];
                if ($event instanceof ErrorEvent) {
                    $errData["message"] = $event->getException()->getMessage();
                }
                $logger->debug("Http request failed, retrying in {$delay}s", $errData);

                // ms > s
                return 1000 * $delay;
            }
        ]);
    }

    protected static function getRetryDelay($retries, AbstractTransferEvent $event, $headerName)
    {
        if (is_null($event->getResponse())
            || !$event->getResponse()->hasHeader($headerName)
        ) {
            return RetrySubscriber::exponentialDelay($retries, $event);
        }

        $retryAfter = $event->getResponse()->getHeader($headerName);
        if (is_numeric($retryAfter)) {
            if ($retryAfter < time() - strtotime('1 day', 0)) {
                return $retryAfter;
            } else {
                return $retryAfter - time();
            }
        }

        if (\Keboola\Utils\isValidDateTimeString($retryAfter, DATE_RFC1123)) {
            $date = \DateTime::createFromFormat(DATE_RFC1123, $retryAfter);
            return $date->getTimestamp() - time();
        }

        return RetrySubscriber::exponentialDelay($retries, $event);
    }
}
