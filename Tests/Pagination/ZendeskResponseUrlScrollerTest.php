<?php
/**
 * @author Erik Zigo <erik.zigo@keboola.com>
 */

use Keboola\Juicer\Client\RestClient,
	Keboola\Juicer\Config\JobConfig,
	Keboola\Juicer\Pagination\ZendeskResponseUrlScroller;

class ZendeskResponseUrlScrollerTest extends ResponseScrollerTestCase
{
	public function testGetNextRequestStop()
	{
		$now = new DateTime();
		$pagingStart = clone $now;

		$client = RestClient::create();
		$config = $this->getConfig();

		$scroller = new ZendeskResponseUrlScroller(['urlKey' => 'next_page']);

		for ($i=0; $i<4; $i++) {
			$step = round(ZendeskResponseUrlScroller::NEXT_PAGE_FILTER_MINUTES * 0.5);
			$pagingStart->modify(sprintf('-%d minutes', $step));


			$response = new \stdClass();
			$response->data = array_fill(0, 10, (object) ['key' => 'value']);
			$response->next_page = 'test?start_time=' . $pagingStart->getTimestamp();

			$next = $scroller->getNextRequest($client, $config, $response, $response->data);

			if (!$i) {
				$this->assertFalse($next);
			} else {
				if (!$next instanceof \Keboola\Juicer\Client\RestRequest) {
					$this->fail('ZendeskResponseUrlScroller::getNextRequest should return new RestRequest');
				}
			}
		}
	}

	public function testGetNextRequest()
	{
		$client = RestClient::create();
		$config = $this->getConfig();

		$scroller = new ZendeskResponseUrlScroller(['urlKey' => 'next']);

		$response = new \stdClass();
		$response->data = array_fill(0, 10, (object) ['key' => 'value']);
		$response->next = 'test?page=2';

		$next = $scroller->getNextRequest($client, $config, $response, $response->data);
		$expected = $client->createRequest([
			'endpoint' => 'test?page=2'
		]);
		self::assertEquals($expected, $next);

		$responseLast = new \stdClass();
		$responseLast->data = array_fill(0, 10, (object) ['key' => 'value']);

		$last = $scroller->getNextRequest($client, $config, $responseLast, $responseLast->data);
		self::assertEquals(false, $last);
	}

	public function testGetNextRequestNested()
	{
		$client = RestClient::create();
		$config = $this->getConfig();

		$scroller = new ZendeskResponseUrlScroller(['urlKey' => 'pagination.next']);

		$response = (object) [
			'pagination' => (object) [
				'next' => 'test?page=2',
				'prev' => 'test?page=0' // Not used, just for usecase demo
			]
		];

		$next = $scroller->getNextRequest($client, $config, $response, []);
		$expected = $client->createRequest([
			'endpoint' => 'test?page=2'
		]);
		self::assertEquals($expected, $next);
	}

	public function testGetNextRequestParams()
	{
		$client = RestClient::create();
		$config = $this->getConfig();

		$response = new \stdClass();
		$response->data = array_fill(0, 10, (object) ['key' => 'value']);
		$response->next = 'test?page=2';

		$scroller = new ZendeskResponseUrlScroller(['urlKey' => 'next', 'includeParams' => true]);

		$next = $scroller->getNextRequest($client, $config, $response, $response->data);
		$expected = $client->createRequest([
			'endpoint' => 'test?page=2',
			'params' => [
				'a' => 1,
				'b' => 2
			]
		]);
		self::assertEquals($expected, $next);
	}

	public function testGetNextRequestQuery()
	{
		$client = RestClient::create();
		$config = $this->getConfig();

		$response = (object) [
			'data' => [],
			'scroll' => '?page=2&q=v'
		];

		$scroller = new ZendeskResponseUrlScroller([
			'urlKey' => 'scroll',
			'paramIsQuery' => true
		]);

		$nextRequest = $scroller->getNextRequest($client, $config, $response, $response->data);
		$expected = $client->createRequest([
			'endpoint' => 'test',
			'params' => [
				'page' => 2,
				'q' => 'v'
			]
		]);
		self::assertEquals($expected, $nextRequest);
	}

	public function testGetNextRequestQueryParams()
	{
		$client = RestClient::create();
		$config = $this->getConfig();

		$response = (object) [
			'data' => [],
			'scroll' => '?page=2&b=v'
		];

		$scroller = new ZendeskResponseUrlScroller([
			'urlKey' => 'scroll',
			'paramIsQuery' => true,
			'includeParams' => true
		]);

		$nextRequest = $scroller->getNextRequest($client, $config, $response, $response->data);
		$expected = $client->createRequest([
			'endpoint' => 'test',
			'params' => [
				'page' => 2,
				'a' => 1,
				'b' => 'v'
			]
		]);
		self::assertEquals($expected, $nextRequest);

	}
}
