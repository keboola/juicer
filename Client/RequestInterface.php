<?php

namespace Keboola\Juicer\Client;

/**
 *
 */
interface RequestInterface
{
	/**
	 *
	 *
	 */
// 	public function setEndpoint($endpoint);

	public function getEndpoint();

// 	public function setParams(array $params);

	public function getParams();

	public static function create(array $config);
}
