<?php

declare(strict_types=1);

namespace Keboola\Juicer\Client;

use Keboola\Juicer\Exception\UserException;

class RestRequest
{
    protected string $method = 'GET';

    protected array $headers = [];

    protected string $endpoint;

    protected array $params = [];

    /**
     * RestRequest constructor.
     * @param array $config
     * [
     *   'endpoint' => string, required
     *   'params' => array,
     *   'method' => *GET*|POST|FORM
     *   'headers' => array
     * ]
     * @throws UserException
     */
    public function __construct(array $config)
    {
        if (!isset($config['endpoint']) || !is_string($config['endpoint'])) {
            throw new UserException('The "endpoint" property must be specified in request as a string.');
        }
        $this->endpoint = $config['endpoint'];
        if (!empty($config['params'])) {
            if (!is_array($config['params'])) {
                throw new UserException('The "params" property must be an array.');
            }
            $this->params = $config['params'];
        }
        if (!empty($config['headers'])) {
            if (!is_array($config['headers'])) {
                throw new UserException('The "headers" property must be an array.');
            }
            $this->headers = $config['headers'];
        }
        if (!empty($config['method'])) {
            if (!is_string($config['method']) || !in_array(strtoupper($config['method']), ['GET', 'POST', 'FORM'])) {
                throw new UserException('The "method" property must be on of "GET", "POST", "FORM".');
            }
            $this->method = strtoupper($config['method']);
        }
    }

    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    public function getParams(): array
    {
        return $this->params;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * @return string METHOD endpoint query/JSON params
     */
    public function __toString(): string
    {
        return join(' ', [
            $this->getMethod(),
            $this->getEndpoint(),
            $this->getMethod() === 'GET'
                ? http_build_query($this->getParams())
                : json_encode($this->getParams(), JSON_PRETTY_PRINT),
        ]);
    }
}
