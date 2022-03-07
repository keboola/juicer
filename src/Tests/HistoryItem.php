<?php

declare(strict_types=1);

namespace Keboola\Juicer\Tests;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;
use UnexpectedValueException;

/**
 * One item from the HistoryContainer
 */
class HistoryItem
{
    private RequestInterface $request;
    private ?ResponseInterface $response;
    private ?Throwable $error;
    private array $options;

    /**
     * See GuzzleHttp\Middleware::history
     */
    public static function fromArray(array $array): self
    {
        return new self(
            $array['request'],
            $array['response'],
            $array['error'],
            $array['options']
        );
    }

    public function __construct(
        RequestInterface $request,
        ?ResponseInterface $response,
        ?Throwable $error,
        array $options
    ) {
        $this->request = $request;
        $this->response = $response;
        $this->error = $error;
        $this->options = $options;
    }

    public function getRequest(): RequestInterface
    {
        return $this->request;
    }

    public function hasResponse(): bool
    {
        return $this->response !== null;
    }

    public function getResponse(): ResponseInterface
    {
        if (!$this->response) {
            throw new UnexpectedValueException('Response is not set.');
        }

        return $this->response;
    }

    public function hasError(): bool
    {
        return $this->error !== null;
    }

    public function getError(): Throwable
    {
        if (!$this->error) {
            throw new UnexpectedValueException('Error is not set.');
        }

        return $this->error;
    }

    public function getOptions(): array
    {
        return $this->options;
    }
}
