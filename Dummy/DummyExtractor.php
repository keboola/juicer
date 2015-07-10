<?php
namespace Keboola\Juicer\Dummy;

use Keboola\Juicer\Extractor\Extractors\JsonExtractor;
use	Keboola\Juicer\Config\Config;
use	GuzzleHttp\Client;

class DummyExtractor extends JsonExtractor
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
		$parser = $this->getParser($config);
		foreach($config->getJobs() as $job) {
			$job = new DummyExtractorJob($job, $client, $parser);
			$job->run();
		}


		## get out. files and save
	}
}
