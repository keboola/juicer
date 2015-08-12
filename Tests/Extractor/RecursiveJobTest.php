<?php

use	Keboola\Juicer\Config\JobConfig,
	Keboola\Juicer\Config\Configuration,
	Keboola\Juicer\Client\RestClient,
	Keboola\Juicer\Parser\Json,
	Keboola\Juicer\Pagination\ResponseUrlScroller,
	Keboola\Juicer\Extractor\RecursiveJob;

use	Keboola\Json\Parser;
use	Keboola\Temp\Temp;

use	GuzzleHttp\Client,
	GuzzleHttp\Message\Response,
	GuzzleHttp\Stream\Stream,
	GuzzleHttp\Subscriber\Mock,
	GuzzleHttp\Subscriber\History;
// mock guzzle, do 2 pages with run, check output
// recursivejobtest too w/ scroller reset
class RecursiveJobTest extends ExtractorTestCase
{
	public function testCreateChild()
	{

	}

	/**
	 * Initializes a parent job and runs the child
	 * This is a pretty awkward test! Useful to rework it for better testing though!
	 */
	public function testParse()
	{
		$temp = new Temp('recursion');
		$configuration = new Configuration(__DIR__ . '/../data/recursive', 'test', $temp);

// 		var_dump(array_values($configuration->getConfig()->getJobs())[0]);
		$jobConfig = array_values($configuration->getConfig()->getJobs())[0];

		$parser = Json::create($configuration->getConfig(), $this->getLogger('test', true), $temp);

		$client = RestClient::create();

		$parentBody = '[
				{"field": "data", "id": 1},
				{"field": "more", "id": 2}
		]';

		$detail = '[
				{"detail": "something"}
		]';

		$mock = new Mock([
			new Response(200, [], Stream::factory($parentBody)),
			new Response(200, [], Stream::factory($detail)),
			new Response(200, [], Stream::factory($detail))
		]);
		$client->getClient()->getEmitter()->attach($mock);

// 		$history = new History();
// 		$client->getClient()->getEmitter()->attach($history);

		$job = new RecursiveJob($jobConfig, $client, $parser);

		$job->run();

// 		echo $history;

		$this->assertEquals(['tickets_export', 'comments'], array_keys($parser->getResults()));
	}
}
