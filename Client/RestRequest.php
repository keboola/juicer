<?php

namespace Keboola\Juicer\Client;

use Keboola\Juicer\Exception\UserException;

/**
 *
 */
class RestRequest extends Request implements RequestInterface
{
    /**
     * @var string
     */
    protected $method;

    /**
     * @var array
     */
    protected $headers;

    public function __construct($endpoint, array $params = [], $method = 'GET', array $headers = [])
    {
        parent::__construct($endpoint, $params);
        $this->method = $method;
        $this->headers = $headers;
    }

    /**
     * @return string
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * @return array
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * [
     *   'endpoint' => string, required
     *   'params' => array,
     *   'method' => *GET*|POST|FORM
     *   'headers' => array
     * ]
     * @param array $config
     * @return static
     */
    public static function create(array $config)
    {
        self::validateConfig($config);

        return new static(
            $config['endpoint'],
            empty($config['params']) ? [] : $config['params'],
            empty($config['method']) ? 'GET' : $config['method'],
            empty($config['headers']) ? [] : $config['headers']
        );
    }

    protected static function validateConfig(array $config)
    {
        foreach([
            'params' => 'array',
            'headers' => 'array',
            'endpoint' => 'string',
            'method' => 'string'
        ] as $key => $type) {
            if (!empty($config[$key]) && gettype($config[$key]) != $type) {
                throw new UserException("Request {$key} must be an {$type}");
            }
        }
    }

    /**
     * @return string METHOD endpoint query/JSON params
     */
    public function __toString()
    {
        return join(' ', [
            $this->getMethod(),
            $this->getEndpoint(),
            'GET' == $this->getMethod()
                ? http_build_query($this->getParams())
                : json_encode($this->getParams(), JSON_PRETTY_PRINT)
        ]);
    }
}
