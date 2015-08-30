<?php

use	Keboola\Juicer\Config\JobConfig,
	Keboola\Juicer\Config\Configuration,
	Keboola\Juicer\Client\RestClient,
	Keboola\Juicer\Parser\Json,
	Keboola\Juicer\Pagination\ResponseUrlScroller,
	Keboola\Juicer\Extractor\RecursiveJob,
	Keboola\Juicer\Common\Logger;

use	Keboola\Json\Parser;
use	Keboola\Temp\Temp;

use	GuzzleHttp\Client,
	GuzzleHttp\Message\Response,
	GuzzleHttp\Stream\Stream,
	GuzzleHttp\Subscriber\Mock,
	GuzzleHttp\Subscriber\History;

/**
 * @todo testCreateChild
 * @todo scroll (w/ reset)
 */
class RecursiveJobTest extends ExtractorTestCase
{
	/**
	 * Initializes a parent job and runs the child
	 * This is a pretty awkward test! Useful to rework it for better testing though!
	 */
	public function testParse()
	{
		list($job, $client, $parser, $history) = $this->getJob();

		$parentBody = '[
				{"field": "data", "id": 1},
				{"field": "more", "id": 2}
		]';
		$detail1 = '[
				{"detail": "something", "subId": 1}
		]';
		$detail2 = '[
				{"detail": "somethingElse", "subId": 1},
				{"detail": "another", "subId": 2}
		]';
		$subDetail = '[{"grand": "child"}]';

		$mock = new Mock([
			new Response(200, [], Stream::factory($parentBody)),
			new Response(200, [], Stream::factory($detail1)),
			new Response(200, [], Stream::factory($subDetail)),
			new Response(200, [], Stream::factory($detail2)),
			new Response(200, [], Stream::factory($subDetail)),
			new Response(200, [], Stream::factory($subDetail))
		]);
		$client->getClient()->getEmitter()->attach($mock);


		$job->run();

		$urls = [];
		foreach($history as $item) {
			$urls[] = $item['request']->getUrl();
		}

		$this->assertEquals(
			[
				"exports/tickets.json",
				"tickets/1/comments.json",
				"third/level/1/1.json",
				"tickets/2/comments.json",
				"third/level/2/1.json",
				"third/level/2/2.json"
			],
			$urls
		);

		// Assert all three levels were parsed to their respective tables
		$this->assertEquals(['tickets_export', 'comments', 'subd'], array_keys($parser->getResults()));
	}

	/**
	 * @expectedException \Keboola\Juicer\Exception\UserException
	 * @expectedExceptionMessage No value found for 1:id in parent result. (level: 1)
	 */
	public function testWrongResponse()
	{
		list($job, $client) = $this->getJob();

		$parentBody = '[
				{"field": "data", "id": 1},
				{"field": "more"}
		]';
		$detail1 = '[
				{"detail": "something", "subId": 1}
		]';
		$detail2 = '[
				{"detail": "somethingElse", "subId": 1},
				{"detail": "another", "subId": 2}
		]';
		$subDetail = '[{"grand": "child"}]';

		$mock = new Mock([
			new Response(200, [], Stream::factory($parentBody)),
			new Response(200, [], Stream::factory($detail1)),
			new Response(200, [], Stream::factory($subDetail)),
// 			new Response(200, [], Stream::factory($detail2)),
		]);
		$client->getClient()->getEmitter()->attach($mock);

		$job->run();
	}

	/**
	 * I'm not too sure this is optimal!
	 * If it looks stupid, but works, it ain't stupid!
	 */
	public function getJob()
	{
		$temp = new Temp('recursion');
		$configuration = new Configuration(__DIR__ . '/../data/recursive', 'test', $temp);

		$jobConfig = array_values($configuration->getConfig()->getJobs())[0];

		$parser = Json::create($configuration->getConfig(), $this->getLogger('test', true), $temp);

		$client = RestClient::create();

		$history = new History();
		$client->getClient()->getEmitter()->attach($history);

		$job = new RecursiveJob($jobConfig, $client, $parser);

		return [
			$job,
			$client,
			$parser,
			$history,
			$jobConfig
		];
	}
}
