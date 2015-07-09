<?php

namespace Keboola\ExtractorBundle\Parser;

use	Keboola\Temp\Temp;

/**
 * Base Parser class
 */
class Parser
{
	private $temp;

	/**
	 * @return Temp $temp
	 */
	protected function getTemp()
	{
		if(!($this->temp instanceof Temp)) {
			$this->temp = new Temp("ex-parser-data");
		}
		return $this->temp;
	}
}
