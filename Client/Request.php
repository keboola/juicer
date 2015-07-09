<?php

namespace Keboola\ExtractorBundle\Client;

use	Keboola\ExtractorBundle\Exception\ApplicationException as Exception;
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
