<?php

namespace Keboola\Juicer\Client;

/**
 *
 */
class SoapRequest extends Request
{
	protected $type = "soap";

	private $soapFunction, $params, $options, $inputHeader;

	/**
	 * @todo $params optional?
	 */
	public function __construct($soapFunction, array $params, $options = null, $inputHeader = null)
	{
		$this->soapFunction = $soapFunction;
		$this->params = $params;
		$this->options = $options;
		$this->inputHeader = $inputHeader;
	}

	/**
	 * @deprecated
	 */
	public function getRequest()
	{
		return [
			"function" => $this->soapFunction,
			"params" => $this->params,
			"options" => $this->options,
			"inputHeader" => $this->inputHeader
		];
	}

	public function setParams(array $params)
	{
		$this->params = $params;
	}

	public function setOptions($options)
	{
		$this->options = $options;
	}

	public function setInputHeader($header)
	{
		$this->inputHeader = $header;
	}

	public function getFunction()
	{
		return $this->soapFunction;
	}

	public function getEndpoint()
	{
		return $this->getFunction();
	}

	public function getParams()
	{
		return $this->params;
	}

	public function getOptions()
	{
		return $this->options;
	}

	public function getInputHeader()
	{
		return $this->inputHeader;
	}

	/**
	 * @todo Actually use the request object?
	 * Should perhaps return the response straight away (call it self::call() or so)
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
			empty($config['options']) ? null : $config['options'],
			empty($config['inputHeader']) ? null : $config['inputHeader']
		);
	}
}
