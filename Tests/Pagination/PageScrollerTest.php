<?php

use	Keboola\Juicer\Client\RestClient,
	Keboola\Juicer\Config\JobConfig,
	Keboola\Juicer\Pagination\PageScroller;

class PageScrollerTest extends ExtractorTestCase
{
	public function testGetNextRequest()
	{
		$client = RestClient::create();
		$config = new JobConfig('test', [
			'endpoint' => 'test',
			'params' => [
				'a' => 1,
				'b' => 2
			]
		]);

		$scroller = new PageScroller();

		$response = new \stdClass();
		$response->data = array_fill(0, 10, (object) ['key' => 'value']);

		$next = $scroller->getNextRequest($client, $config, $response, $response->data);
		$expected = $client->createRequest([
			'endpoint' => 'test',
			'params' => [
				'a' => 1,
				'b' => 2,
				'page' => 2
			]
		]);
		$this->assertEquals($expected, $next);

		$next2 = $scroller->getNextRequest($client, $config, $response, $response->data);
		$expected2 = $client->createRequest([
			'endpoint' => 'test',
			'params' => [
				'a' => 1,
				'b' => 2,
				'page' => 3
			]
		]);
		$this->assertEquals($expected2, $next2);

		// Empty response
		$responseUnderLimit = new \stdClass();
		$responseUnderLimit->data = [];
		$next3 = $scroller->getNextRequest($client, $config, $responseUnderLimit, $responseUnderLimit->data);
		$this->assertEquals(false, $next3);

		// Scroller limit higher than response size
		$scrollerLimit = new PageScroller('page', 100);
		$next4 = $scrollerLimit->getNextRequest($client, $config, $response, $response->data);
		$this->assertEquals(false, $next4);
	}

	public function getNextRequestPost()
	{

		$client = RestClient::create();
		$config = new JobConfig('test', [
			'endpoint' => 'test',
			'params' => [
				'a' => 1,
				'b' => 2
			],
			'method' => 'POST'
		]);

		$scroller = new PageScroller();

		$response = new \stdClass();
		$response->data = array_fill(0, 10, (object) ['key' => 'value']);
		$next = $scroller->getNextRequest($client, $config, $response, $response->data);
		$expected = $client->createRequest([
			'endpoint' => 'test',
			'params' => [
				'a' => 1,
				'b' => 2,
				'page' => 2
			],
			'method' => 'POST'
		]);
		$this->assertEquals($expected, $next);
	}
}
