<?php

namespace Keboola\Juicer\Client;

/**
 *
 */
class RestRequest extends Request
{
	protected $method;

	public function __construct($endpoint, array $params = [], $method = 'GET')
	{
		parent::__construct($endpoint, $params);
		$this->method = $method;
	}

	public function getMethod()
	{
		return $this->method;
	}

	/**
	 * @todo Actually use the request object?
	 * Should perhaps return the response straight away (call it self::call() or so)
	 * @param string $endpoint REST endpoint or SOAP function
	 * @param array parameters
	 * @param array REST method or SOAP options+inputHeader
	 * @return RequestInterface
	 */
// 	public static function create($endpoint, $params = [], $options = [])
	public static function create(array $config)
	{
		return new static(
			$config['endpoint'],
			empty($config['params']) ? [] : $config['params'],
			empty($config['method']) ? 'GET' : $config['method']
		);
	}
}
