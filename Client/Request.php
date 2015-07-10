<?php

namespace Keboola\Juicer\Client;

use	Keboola\Juicer\Exception\ApplicationException as Exception;
/**
 *
 */
class Request
{
	protected $type;

	public function getType() {
		return $this->type();
	}

	public function getRequest() {}
}
