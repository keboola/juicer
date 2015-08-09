<?php

namespace Keboola\Juicer\Client;

use	Keboola\Juicer\Exception\ApplicationException as Exception;
/**
 *
 */
abstract class Request
{
	protected $endpoint;

	protected $params;

	public function __construct($endpoint, array $params = [])
	{
		$this->endpoint = $endpoint;
		$this->params = $params;
	}

// 	/**
// 	 * @todo Actually use the request object?
// 	 * Should perhaps return the response straight away (call it self::call() or so)
// 	 * @param string $endpoint REST endpoint or SOAP function
// 	 * @param array parameters
// 	 * @param array REST method or SOAP options+inputHeader
// 	 * @return RequestInterface
// 	 */
// 	public static function create(array $config) {}

	public function getEndpoint()
	{
		return $this->endpoint;
	}

	public function getParams()
	{
		return $this->params;
	}


}
