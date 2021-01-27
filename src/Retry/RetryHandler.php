<?php

declare(strict_types=1);

namespace Keboola\Juicer\Retry;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Throwable;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use function Keboola\Utils\isValidDateTimeString;

class RetryHandler
{
    public const EXPONENTIAL_DELAY_SEED = 1000; // ms

    public const DEFAULT_RETRY_HEADER = 'Retry-After';

    public const DEFAULT_MAX_RETRIES = 10;

    public const DEFAULT_HTTP_CODES = [500, 502, 503, 504, 408, 420, 429];

    public const DEFAULT_CURL_CODES = [
        CURLE_OPERATION_TIMEOUTED,
        CURLE_COULDNT_RESOLVE_HOST,
        CURLE_COULDNT_CONNECT,
        CURLE_SSL_CONNECT_ERROR,
        CURLE_GOT_NOTHING,
        CURLE_RECV_ERROR,
    ];

    private LoggerInterface $logger;

    private int $maxRetries;

    private string $retryHeader;

    private array $curlCodes;

    private array $httpCodes;

    private RequestInterface $lastRequest;

    private ?Throwable $lastException;


    /**
     * Config
     *  - maxRetries: (integer) max retries count
     *  - http
     *      - retryHeader (string) header containing retry time header
     *      - codes (array) list of status codes to retry on
     *  - curl
     *      - codes (array) list of error codes to retry on
     */
    public function __construct(LoggerInterface $logger, array $config)
    {
        $this->logger = $logger;
        $this->maxRetries = isset($config['maxRetries']) ? (int) $config['maxRetries'] : self::DEFAULT_MAX_RETRIES;
        $this->retryHeader = $config['http']['retryHeader'] ?? self::DEFAULT_RETRY_HEADER;
        $this->httpCodes = isset($config['http']['codes']) ? $config['http']['codes'] : self::DEFAULT_HTTP_CODES;
        $this->curlCodes = isset($config['curl']['codes']) ? $config['curl']['codes'] : self::DEFAULT_CURL_CODES;
    }

    /**
     * Returns true if the request is to be retried.
     */
    public function decider(
        int $retries,
        RequestInterface $request,
        ?ResponseInterface $response,
        ?Throwable $exception
    ): bool {
        $this->lastRequest = $request;
        $this->lastException = $exception;
        if ($retries >= $this->maxRetries) {
            return false;
        }

        // Http errors
        if ($response && in_array($response->getStatusCode(), $this->httpCodes, true)) {
            return true;
        }

        // Curl errors
        if ($exception instanceof ConnectException || $exception instanceof RequestException) {
            $curlErrorNo =  $exception->getHandlerContext()['errno'] ?? null;
            if ($curlErrorNo && in_array($curlErrorNo, $this->curlCodes, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns the number of milliseconds to delay.
     */
    public function delay(
        int $retries,
        ?ResponseInterface $response
    ): int {
        $delayMs = null;
        if ($response && $response->hasHeader($this->retryHeader)) {
            $delayMs = self::delayFromHeader($response->getHeaderLine($this->retryHeader));
        }

        $delayMs = $delayMs ?: self::exponentialDelay($retries);
        $this->logRetry($retries, $this->lastRequest, $response, $this->lastException, $delayMs);
        return $delayMs;
    }

    protected function delayFromHeader(string $retryAfter): ?int
    {
        if (is_numeric($retryAfter)) {
            $retryAfter = (int) $retryAfter;
            if ($retryAfter < time() - strtotime('1 day', 0)) {
                return $retryAfter * 1000;
            } else {
                return ($retryAfter - time())  * 1000;
            }
        }

        if (isValidDateTimeString($retryAfter, DATE_RFC1123)) {
            /** @var \DateTimeImmutable $date */
            $date = \DateTime::createFromFormat(DATE_RFC1123, $retryAfter);
            return ($date->getTimestamp() - time()) * 1000;
        }

        return null;
    }

    protected function exponentialDelay(int $retries): int
    {
        return (int) self::EXPONENTIAL_DELAY_SEED * pow(2, $retries - 1);
    }

    protected function logRetry(
        int $retries,
        RequestInterface $request,
        ?ResponseInterface $response,
        ?Throwable $exception,
        int $delayMs
    ): void {
        $bodyStream = $response ? $response->getBody() : null;
        if ($bodyStream) {
            $bodyStream->rewind();
            $body = $bodyStream->getContents();
        } else {
            $body = null;
        }

        $errData = [
            'http_code' => $response ? $response->getStatusCode() : null,
            'body' => $body,
            'url' =>  (string) $request->getUri(),
        ];

        if ($exception) {
            $errData['exception_class'] = get_class($exception);
            $errData['message'] = $exception->getMessage();
        }

        $this->logger->debug(
            sprintf('Http request failed, retrying in %.1f seconds [%dx].', $delayMs / 1000, $retries),
            $errData
        );
    }
}
