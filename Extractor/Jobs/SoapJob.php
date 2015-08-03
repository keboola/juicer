<?php

namespace Keboola\Juicer\Extractor\Jobs;

use	Keboola\Juicer\Exception\UserException;
use	Keboola\Juicer\Extractor\Job,
	Keboola\Juicer\Common\Logger;
/**
 * {@inheritdoc}
 * This class handles download from SOAP APIs
 */
abstract class SoapJob extends Job
{
	/**
	 * @var \SoapClient
	 */
	protected $client;

	/**
	 * Download an URL from SOAP API and return its body as an object.
	 * Use $this->updateRequest($request) to update the request (ie auth)
	 * between calls, exp. fallback retries etc.
	 *
	 * @param \Keboola\Juicer\Client\SoapRequest $request
	 * @return object response
	 */
	protected function download($request)
	{
		$backoffMaxTry = 8;
		$backoffTry = 0;
		$response = null;
		do {
			if ($backoffTry > 0) {
				sleep(pow(2, $backoffTry));
			}

			$this->updateRequest($request);

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
				if ($backoffTry >= $backoffMaxTry) {
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
}
