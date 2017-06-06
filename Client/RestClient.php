<?php

namespace Keboola\Juicer\Client;

use GuzzleHttp\Exception\RequestException;
use Keboola\Juicer\Exception\UserException;
use Keboola\Juicer\Exception\ApplicationException;
use Keboola\Juicer\Common\Logger;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Message\Request as GuzzleRequest;
use GuzzleHttp\Message\Response;
use GuzzleHttp\Subscriber\Retry\RetrySubscriber;
use GuzzleHttp\Event\AbstractTransferEvent;
use GuzzleHttp\Event\ErrorEvent;
use Keboola\Utils\Exception\JsonDecodeException;

class RestClient extends AbstractClient implements ClientInterface
{
    /**
     * @var Client
     */
    protected $client;

    /**
     * override if the server response isn't UTF-8
     * @var string
     */
    protected $responseEncoding = 'UTF-8';

    const JSON = 'json';
    const XML = 'xml';
    const RAW = 'raw';

    /**
     * @var string
     */
    protected $responseFormat = self::JSON;

    /**
     * RestClient constructor.
     * @param Client $guzzle
     */
    public function __construct(Client $guzzle)
    {
        $this->client = $guzzle;
    }

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
     * @return self
     */
    public static function create($guzzleConfig = [], $retryConfig = [])
    {
        $guzzle = new Client($guzzleConfig);
        $guzzle->getEmitter()->attach(self::createBackoff($retryConfig));

        $guzzle->getEmitter()->on('error', function (ErrorEvent $errorEvent) {
            $errno = $errorEvent->getTransferInfo('errno');
            $error = $errorEvent->getTransferInfo('error');

            if ($errno > 0) {
                throw new UserException(sprintf(
                    "CURL error %d: %s",
                    $errno,
                    $error
                ));
            }
        }, "last");
        return new self($guzzle);
    }

    /**
     * @return Client
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * @param Request|RequestInterface $request
     * @return array|object
     * @throws UserException
     * @throws \Exception
     */
    public function download(RequestInterface $request)
    {
        try {
            $response = $this->client->send($this->getGuzzleRequest($request));
            return $this->getObjectFromResponse($response);
        } catch (BadResponseException $e) {
            // TODO try XML if JSON fails
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
     * @param Response $response
     * @return array|object Should be anything that can result from json_decode
     * @throws ApplicationException
     * @throws UserException
     */
    protected function getObjectFromResponse(Response $response)
    {
        // Format the response
        switch ($this->responseFormat) {
            case self::JSON:
                // Sanitize the JSON
                $body = iconv($this->responseEncoding, 'UTF-8//IGNORE', $response->getBody());
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
            case self::XML:
                try {
                    $xml = new \SimpleXMLElement($response->getBody());
                } catch (\Exception $e) {
                    throw new UserException(
                        "Error decoding the XML response: " . $e->getMessage(),
                        400,
                        $e,
                        ['body' => (string) $response->getBody()]
                    );
                }
                // TODO must be a \stdClass
                return $xml;
            case self::RAW:
                // Or could this be a string?
                $object = new \stdClass;
                $object->body = (string) $response->getBody();
                return $object;
            default:
                throw new ApplicationException("Data format {$this->responseFormat} not supported.");
        }
    }

    /**
     * @param RequestInterface $request
     * @return GuzzleRequest
     * @throws ApplicationException
     * @throws UserException
     */
    protected function getGuzzleRequest(RequestInterface $request)
    {
        if (!$request instanceof RestRequest) {
            throw new ApplicationException("RestClient requires a RestRequest!");
        }

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
     * @param array $config
     * @return RestRequest
     */
    public function createRequest(array $config)
    {
        return RestRequest::create($this->getRequestConfig($config));
    }

    /**
     * Create expontential backoff for GuzzleClient
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
     * @return RetrySubscriber
     */
    private static function createBackoff(array $options)
    {
        $headerName = isset($options['http']['retryHeader']) ? $options['http']['retryHeader'] : 'Retry-After';
        $httpRetryCodes = isset($options['http']['codes']) ? $options['http']['codes'] : [500, 502, 503, 504, 408, 420, 429];
        $maxRetries = isset($options['maxRetries']) ? (int) $options['maxRetries']: 10;

        $curlRetryCodes = isset($options['curl']['codes']) ? $options['curl']['codes'] : [
            CURLE_OPERATION_TIMEOUTED,
            CURLE_COULDNT_RESOLVE_HOST,
            CURLE_COULDNT_CONNECT,
            CURLE_SSL_CONNECT_ERROR,
            CURLE_GOT_NOTHING
        ];

        return new RetrySubscriber([
            'filter' => RetrySubscriber::createChainFilter([
                RetrySubscriber::createStatusFilter($httpRetryCodes),
                RetrySubscriber::createCurlFilter($curlRetryCodes)
            ]),
            'max' => $maxRetries,
            'delay' => function ($retries, AbstractTransferEvent $event) use ($headerName) {
                $delay = self::getRetryDelay($retries, $event, $headerName);

                $errData = [
                    "http_code" => !empty($event->getTransferInfo()['http_code']) ? $event->getTransferInfo()['http_code'] : null,
                    "body" => is_null($event->getResponse()) ? null : (string) $event->getResponse()->getBody(),
                    "url" =>  !empty($event->getTransferInfo()['url']) ? $event->getTransferInfo()['url'] : $event->getRequest()->getUrl(),
                ];
                if ($event instanceof ErrorEvent) {
                    $errData["message"] = $event->getException()->getMessage();
                }
                Logger::log("DEBUG", "Http request failed, retrying in {$delay}s", $errData);

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

    /**
     * @param string $format
     */
    public function setResponseFormat($format)
    {
        $this->responseFormat = $format;
    }

    /**
     * @param $encoding
     */
    public function setResponseEncoding($encoding)
    {
        $this->responseEncoding = $encoding;
    }
}
