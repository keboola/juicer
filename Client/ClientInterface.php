<?php

namespace Keboola\Juicer\Client;

use	Keboola\Juicer\Config\JobConfig;

/**
 *
 */
interface ClientInterface
{
	/**
	 * @param Request $request
	 * @return mixed Raw response as it comes from the client
	 */
	public function download(Request $request);


	/**
	 * Create a request from a jobConfig
	 */
	public function getRequest(JobConfig $config);

	/**
	 *
	 */
	public function getClient();
}
