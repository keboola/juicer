<?php

namespace Keboola\Juicer\Client;

use	Keboola\Juicer\Exception\ApplicationException as Exception;
/**
 *
 */
class SoapRequest extends Request
{
	protected $type = "soap";

	private $soapFunction, $params, $options, $inputHeader;

	public function __construct($soapFunction, $params, $options = null, $inputHeader = null) {
		$this->soapFunction = $soapFunction;
		$this->params = $params;
		$this->options = $options;
		$this->inputHeader = $inputHeader;
	}

	public function getRequest() {
		return array(
						"function" => $this->soapFunction,
						"params" => $this->params,
						"options" => $this->options,
						"inputHeader" => $this->inputHeader
					);
	}

	public function setParams($params) {
		$this->params = $params;
	}

	public function setOptions($options) {
		$this->options = $options;
	}

	public function setInputHeader($header) {
		$this->inputHeader = $header;
	}

	public function getFunction() {
		return $this->soapFunction;
	}

	public function getParams() {
		return $this->params;
	}

	public function getOptions() {
		return $this->options;
	}

	public function getInputHeader() {
		return $this->inputHeader;
	}
}
