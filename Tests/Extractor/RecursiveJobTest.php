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

		$this->assertEquals(
			'"field","id"' . PHP_EOL .
			'"data","1"' . PHP_EOL .
			'"more","2"' . PHP_EOL,
			file_get_contents($parser->getResults()['tickets_export'])
		);

		$this->assertEquals(
			'"detail","subId","parent_id"' . PHP_EOL .
			'"something","1","1"' . PHP_EOL .
			'"somethingElse","1","2"' . PHP_EOL .
			'"another","2","2"' . PHP_EOL,
			file_get_contents($parser->getResults()['comments'])
		);

		$this->assertEquals(
			'"grand","parent_id","parent_subId"' . PHP_EOL .
			'"child","1","1"' . PHP_EOL .
			'"child","2","1"' . PHP_EOL .
			'"child","2","2"' . PHP_EOL,
			file_get_contents($parser->getResults()['subd'])
		);
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

	public function testSamePlaceholder()
	{
		list($job, $client, $parser, $history) = $this->getJob('recursive2');

		$cubes = '{
			"@odata.context": "$metadata#Cubes",
			"value": [
				{
					"Name": "plan_BudgetPlan",
					"Rules": "tl;dr"
				},
				{
					"Name": "SDK.SampleCube",
					"Rules": ""
				}
			]
		}';

		$views = '{
			"@odata.context": "../$metadata#Cubes(\'plan_BudgetPlan\')/Views",
			"value": [
				{
					"Name": "budget_placeholder",
					"SuppressEmptyRows": false
				},
				{
					"Name": "Budget Input Detailed",
					"SuppressEmptyRows": false
				}
			]
		}';

		$results = '{}';

		$mock = new Mock([
			new Response(200, [], Stream::factory($cubes)),
			new Response(200, [], Stream::factory($views)),
			new Response(200, [], Stream::factory($results)),
			new Response(200, [], Stream::factory($results)),
			new Response(200, [], Stream::factory($views)),
			new Response(200, [], Stream::factory($results)),
			new Response(200, [], Stream::factory($results)),
		]);
		$client->getClient()->getEmitter()->attach($mock);

		$job->run();

		$urls = [];
		foreach($history as $item) {
			$urls[] = $item['request']->getUrl();
		}

		$this->assertEquals(
			[
				'Cubes',
				'Cubes(\'plan_BudgetPlan\')/Views',
				'Cubes(\'plan_BudgetPlan\')/Views(\'budget_placeholder\')/tm1.Execute?%24expand=Cells',
				'Cubes(\'plan_BudgetPlan\')/Views(\'Budget%20Input%20Detailed\')/tm1.Execute?%24expand=Cells',
				'Cubes(\'SDK.SampleCube\')/Views',
				'Cubes(\'SDK.SampleCube\')/Views(\'budget_placeholder\')/tm1.Execute?%24expand=Cells',
				'Cubes(\'SDK.SampleCube\')/Views(\'Budget%20Input%20Detailed\')/tm1.Execute?%24expand=Cells',
			],
			$urls
		);
	}

	public function testDataTypeWithId()
	{
		list($job, $client, $parser, $history, $jobConfig) = $this->getJob('recursiveNoType');

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

// 		$children = $jobConfig->getChildJobs();
// 		var_dump(reset($children)->getDataType());
		$this->assertEquals(
			['exports_tickets_json', 'tickets__1_id__comments_json', 'third_level__2_id___id__json'],
			array_keys($parser->getResults())
		);
	}

	/**
	 * I'm not too sure this is optimal!
	 * If it looks stupid, but works, it ain't stupid!
	 */
	public function getJob($dir = 'recursive')
	{
		$temp = new Temp('recursion');
		$configuration = new Configuration(__DIR__ . '/../data/' . $dir, 'test', $temp);

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
