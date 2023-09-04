<?php

declare(strict_types=1);

namespace Keboola\Juicer\Tests;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use Keboola\Juicer\Client\RestClient;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Used to test RestClient in this repo and in the generic-extractor.
 */
class RestClientMockBuilder
{
    /** @var callable|null */
    private $initCallback = null;
    private ?LoggerInterface $logger = null;
    private ?HistoryContainer $history = null;
    private array $responses = [];
    private string $baseUri = 'http://example.com';
    private array $guzzleConfig = [];
    private array $retryConfig = [];
    private array $defaultOptions = [];
    private array $ignoreErrors = [];

    public static function create(): self
    {
        return new self();
    }

    public function getRestClient(): RestClient
    {
        $logger = $this->logger ?? new NullLogger();
        $handler = HandlerStack::create(new MockHandler($this->responses));
        $this->guzzleConfig['handler'] = $handler;
        $restClient = new RestClient(
            $logger,
            $this->baseUri,
            $this->guzzleConfig,
            $this->retryConfig,
            $this->defaultOptions,
            $this->ignoreErrors,
        );

        if ($this->initCallback !== null) {
            ($this->initCallback)($restClient);
        }

        if ($this->history !== null) {
            // To log final state, history middleware must be pushed after all other middlewares.
            $handler->push(Middleware::history($this->history));
        }

        return $restClient;
    }

    public function setInitCallback(callable $initCallback): self
    {
        $this->initCallback = $initCallback;
        return $this;
    }

    public function setBaseUri(string $baseUri): self
    {
        $this->baseUri = $baseUri;
        return $this;
    }

    /**
     * @param ResponseInterface[] $responses
     */
    public function setResponses(array $responses): self
    {
        $this->responses = $responses;
        return $this;
    }

    public function addResponse(ResponseInterface $response, int $repeat = 1): self
    {
        $this->responses[] = $response;
        return $this;
    }

    public function addResponseRepeatedly(int $repetitions, ResponseInterface $response): self
    {
        for ($i = 0; $i < $repetitions; $i++) {
            $this->responses[] = $response;
        }
        return $this;
    }

    public function addResponse200(?string $body = '{"foo": "bar"}'): self
    {
        return $this->addResponse(new Response(200, [], Utils::streamFor($body)));
    }

    public function addResponse404(?string $body = '{"foo": "bar"}'): self
    {
        return $this->addResponse(new Response(404, [], Utils::streamFor($body)));
    }

    public function addResponse503(?string $body = '{"foo": "bar"}'): self
    {
        return $this->addResponse(new Response(503, [], Utils::streamFor($body)));
    }

    public function setHistoryContainer(HistoryContainer $history): self
    {
        $this->history = $history;
        return $this;
    }

    public function setGuzzleConfig(array $guzzleConfig): self
    {
        $this->guzzleConfig = $guzzleConfig;
        return $this;
    }

    public function setRetryConfig(array $retryConfig): self
    {
        $this->retryConfig = $retryConfig;
        return $this;
    }

    public function setRequestDefaultOptions(array $defaultOptions): self
    {
        $this->defaultOptions = $defaultOptions;
        return $this;
    }

    public function setIgnoreErrors(array $ignoreErrors): self
    {
        $this->ignoreErrors = $ignoreErrors;
        return $this;
    }

    public function addIgnoredError(int $error): self
    {
        $this->ignoreErrors[] = $error;
        return $this;
    }
}
