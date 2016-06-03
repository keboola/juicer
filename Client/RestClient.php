<?php

namespace Keboola\Juicer\Client;

use Keboola\Juicer\Exception\UserException,
    Keboola\Juicer\Exception\ApplicationException,
    Keboola\Juicer\Config\JobConfig,
    Keboola\Juicer\Common\Logger;
use GuzzleHttp\Client,
    GuzzleHttp\Exception\BadResponseException,
    GuzzleHttp\Exception\ClientException,
    GuzzleHttp\Message\Request as GuzzleRequest,
    GuzzleHttp\Message\Response,
    GuzzleHttp\Subscriber\Retry\RetrySubscriber,
    GuzzleHttp\Event\AbstractTransferEvent,
    GuzzleHttp\Event\ErrorEvent;
use Keboola\Utils\Utils,
    Keboola\Utils\Exception\JsonDecodeException;

/**
 *
 */
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

    protected static $defaultRetryCodes = [500, 502, 503, 504, 408, 420, 429];

    /**
     * @var string
     */
    protected $responseFormat = self::JSON;

    public function __construct(Client $guzzle)
    {
        $this->client = $guzzle;
    }

    /**
     * @param array $defaults GuzzleHttp\Client defaults
     * @param array $retryConfig
     *      maxRetries => Maximum retries
     *      httpCodes => List of HTTP error codes to retry on
     *      headerName => Header containing the retry delay
     * @return self
     */
    public static function create($defaults = [], $retryConfig = null)
    {
        $retries = $retryConfig['maxRetries'] ?? 10;
        $httpCodes = $retryConfig['httpCodes'] ?? self::$defaultRetryCodes;
        $delayHeaderName = $retryConfig['headerName'] ?? 'Retry-After';

        $guzzle = new Client($defaults);
        $guzzle->getEmitter()->attach(self::getBackoff($retries, $httpCodes, $delayHeaderName));
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
     * @param Request $request
     * @return object|array
     */
    public function download(RequestInterface $request)
    {
        try {
            $response = $this->client->send($this->getGuzzleRequest($request));
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
        }

        return $this->getObjectFromResponse($response);
    }

    /**
     * @param Response $response
     * @return object|array Should be anything that can result from json_decode
     */
    protected function getObjectFromResponse(Response $response)
    {
        // Format the response
        switch ($this->responseFormat) {
            case self::JSON:
                // Sanitize the JSON
                $body = iconv($this->responseEncoding, 'UTF-8//IGNORE', $response->getBody());
                try {
                    $decoded = Utils::json_decode($body, false, 512, 0, true, true);
                } catch(JsonDecodeException $e) {
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
                } catch(\Exception $e) {
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
     */
    protected function getGuzzleRequest(RequestInterface $request)
    {
        if (!$request instanceof RestRequest) {
            throw new ApplicationException("RestClient requires a RestRequest!");
        }

        switch ($request->getMethod()) {
            case 'GET':
                $method = $request->getMethod();
                $endpoint = Utils::buildUrl($request->getEndpoint(), $request->getParams());
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
     * Returns an exponential backoff (prefers Retry-After header) for GuzzleClient (4.*).
     * Use: `$client->getEmitter()->attach($this->getBackoff());`
     * @param int $max
     * @param array $retryCodes
     * @return RetrySubscriber
     */
    public static function getBackoff($max = 10, array $retryCodes = null, $headerName = 'Retry-After')
    {
        $retryCodes = is_null($retryCodes) ? self::$defaultRetryCodes : $retryCodes;

        return new RetrySubscriber([
            'filter' => RetrySubscriber::createChainFilter([
                RetrySubscriber::createStatusFilter($retryCodes),
                RetrySubscriber::createCurlFilter()
            ]),
            'max' => $max,
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
        if (
            is_null($event->getResponse())
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

        if (Utils::isValidDateTimeString($retryAfter, DATE_RFC1123)) {
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
     * @param string $format
     */
    public function setResponseEncoding($encoding)
    {
        $this->responseEncoding = $encoding;
    }
}
