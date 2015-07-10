<?php

namespace Keboola\Juicer\Client;

/**
 *
 */
interface ClientInterface
{
	/**
	 * @param \Keboola\Juicer\Client\SoapRequest|\GuzzleHttp\Message\Request $request
	 * @return mixed Raw response as it comes from the client
	 */
	public function download($request);
}
