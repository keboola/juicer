<?php
namespace Keboola\Juicer\Dummy;

use Keboola\Juicer\Extractor\Jobs\JsonRecursiveJob;
use	Keboola\Juicer\Config\Config;
use	GuzzleHttp\Client;

class DummyExtractorJob extends JsonRecursiveJob
{
	/**
	 * Setup the extractor and loop through each job from $config["jobs"] and run the job
	 *
	 * @param Config $config
	 * @return Table[]
	 */
	protected function firstPage()
	{
		var_dump($this);
		die();

	}
}
