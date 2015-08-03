<?php

namespace Keboola\Juicer\Client;

use	Keboola\Juicer\Exception\UserException,
	Keboola\Juicer\Exception\ApplicationException,
	Keboola\Juicer\Config\JobConfig,
	Keboola\Juicer\Common\Logger;
use	Keboola\Utils\Utils;
use	SoapClient;

/**
 *
 */
class SoapClient
{
	/**
	 * @var SoapClient
	 */
	protected $client;

	/**
	 * @var int
	 */
	protected $backoffTryCount;

	public function __construct(SoapClient $client, $backoffTryCount = 8)
	{
		$this->client = $client;
		$this->backoffTryCount = $backoffTryCount;
	}

	public static function create($wsdl = null, $options = [])
	{
		return new self(SoapClient($wsdl, $options));
	}

	/**
	 * @param Request $request
	 * @return mixed
	 */
	public function download(Request $request)
	{
		$backoffTry = 0;
		$response = null;
		do {
			if ($backoffTry > 0) {
				sleep(pow(2, $backoffTry));
			}

			try {
				$response = $this->client->__soapCall($request->getFunction(), $request->getParams(), $request->getOptions(), $request->getInputHeader(), $outputHeaders);
			} catch(\SoapFault $e) {
				$backoffTry++;
					$errData = array(
						"code" => $e->getCode(),
						"message" => $e->getMessage(),
						"faultcode" => isset($e->faultcode) ? $e->faultcode : null,
						"faultstring" => isset($e->faultstring) ? $e->faultstring : null,
						"detail" => isset($e->detail) ? ((array) $e->detail) : null,
					);

				// Do not retry if max. retry count is reached OR the error isn't on server(TODO?):  || $errData["faultcode"] == "SOAP-ENV:Client"
				if ($backoffTry >= $this->backoffTryCount) {
					$e = new UserException("Soap call failed:" . $e->getCode() . ": " . $e->getMessage(), 400, $e);
					$e->setData($errData);
					throw $e;
				} else {
					Logger::log("debug", "Soap call error, retrying:" . $e->getCode() . ": " . $e->getMessage(), $errData);
				}
			}
		} while ($response === null);

		return $response;
	}

	public function getRequest(JobConfig $jobConfig)
	{
		return SoapRequest::create($jobConfig->getConfig());
	}

	/**
	 * @return client
	 */
	public function getClient()
	{
		return $this->client;
	}
}
