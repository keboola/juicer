<?php

namespace Keboola\Juicer\Client;

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
	 * @param string $endpoint REST endpoint or SOAP function
	 * @param array parameters
	 * @param array REST method or SOAP options+inputHeader
	 * @return RequestInterface
	 */
	public static function create(array $config)
	{
		return new static(
			$config['endpoint'],
			empty($config['params']) ? [] : $config['params'],
			empty($config['method']) ? 'GET' : $config['method']
		);
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
