<?php
namespace Keboola\ExtractorBundle\Dummy;

use Keboola\ExtractorBundle\Extractor\Extractor;
use	Keboola\ExtractorBundle\Config\Config;
use	GuzzleHttp\Client;

class DummyExtractor extends Extractor
{
	/**
	 * Setup the extractor and loop through each job from $config["jobs"] and run the job
	 *
	 * @param Config $config
	 * @return Table[]
	 */
	public function run(Config $config)
	{
		$client = new Client();
		foreach($config->getJobs() as $job) {
			var_dump($job);
		}

	}
}
