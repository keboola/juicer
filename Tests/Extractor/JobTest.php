<?php

use	Keboola\Juicer\Config\JobConfig,
	Keboola\Juicer\Client\RestClient,
	Keboola\Juicer\Parser\Json,
	Keboola\Juicer\Pagination\ResponseUrlScroller,
	Keboola\Juicer\Extractor\Job;

use	Keboola\Json\Parser;

use	GuzzleHttp\Client,
	GuzzleHttp\Message\Response,
	GuzzleHttp\Stream\Stream,
	GuzzleHttp\Subscriber\Mock,
	GuzzleHttp\Subscriber\History;
// mock guzzle, do 2 pages with run, check output
// recursivejobtest too w/ scroller reset
class JobTest extends ExtractorTestCase
{
	public function testRun()
	{
		$config = JobConfig::create([
			'endpoint' => 'api/ep',
			'params' => [
				'first' => 'one',
				'second' => 2
			]
		]);

		$first = '{
			"data": [
				{"field": "one"},
				{"field": "two"}
			],
			"next_page": "api/ep/2"
		}';

		$second = '{
			"data": [
				{"field": "three"},
				{"field": "four"}
			],
			"next_page": ""
		}';

		$mock = new Mock([
			new Response(200, [], Stream::factory($first)),
			new Response(200, [], Stream::factory($second))
		]);

		$history = new History();

		$client = RestClient::create();
		$client->getClient()->getEmitter()->attach($mock);
		$client->getClient()->getEmitter()->attach($history);

		$parser = new Json(new Parser($this->getLogger('job', true)));

		$job = new Job($config, $client, $parser);
		$job->setScroller(ResponseUrlScroller::create([]));

		$job->run();

		$this->assertEquals(
			'"field"
"one"
"two"
"three"
"four"
',
			file_get_contents($parser->getResults()['api_ep'])
		);
	}
}
