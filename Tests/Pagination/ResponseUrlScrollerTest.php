<?php

use	Keboola\Juicer\Client\RestClient,
	Keboola\Juicer\Config\JobConfig,
	Keboola\Juicer\Pagination\ResponseUrlScroller;

class ResponseUrlScrollerTest extends ExtractorTestCase
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

		$scroller = new ResponseUrlScroller('next');

		$response = new \stdClass();
		$response->data = array_fill(0, 10, (object) ['key' => 'value']);
		$response->next = 'test?page=2';

		$next = $scroller->getNextRequest($client, $config, $response, $response->data);
		$expected = $client->createRequest([
			'endpoint' => 'test?page=2'
		]);
		$this->assertEquals($expected, $next);

		$responseLast = new \stdClass();
		$responseLast->data = array_fill(0, 10, (object) ['key' => 'value']);

		$last = $scroller->getNextRequest($client, $config, $responseLast, $responseLast->data);
		$this->assertEquals(false, $last);
	}

	public function testGetNextRequestParams()
	{
		$client = RestClient::create();
		$config = new JobConfig('test', [
			'endpoint' => 'test',
			'params' => [
				'a' => 1,
				'b' => 2
			]
		]);

		$response = new \stdClass();
		$response->data = array_fill(0, 10, (object) ['key' => 'value']);
		$response->next = 'test?page=2';

		$scrollerParams = new ResponseUrlScroller('next', true);

		$nextParams = $scrollerParams->getNextRequest($client, $config, $response, $response->data);
		$expectedParams = $client->createRequest([
			'endpoint' => 'test?page=2',
			'params' => [
				'a' => 1,
				'b' => 2
			]
		]);
		$this->assertEquals($expectedParams, $nextParams);
	}
}
