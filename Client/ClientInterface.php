<?php

namespace Keboola\ExtractorBundle\Client;

/**
 *
 */
interface ClientInterface
{
	/**
	 * @param \Keboola\ExtractorBundle\Client\SoapRequest|\GuzzleHttp\Message\Request $request
	 * @return mixed Raw response as it comes from the client
	 */
	public function download($request);
}
