<?php

use	Keboola\Juicer\Client\RestRequest,
	Keboola\Juicer\Client\RestClient,
	Keboola\Juicer\Config\JobConfig,
	Keboola\Juicer\Common\Logger;
use	GuzzleHttp\Client,
	GuzzleHttp\Message\Response,
	GuzzleHttp\Stream\Stream,
	GuzzleHttp\Subscriber\Mock,
	GuzzleHttp\Subscriber\History;

class RestClientTest extends ExtractorTestCase
{

	public function testCreateRequest()
	{
		$arr = [
			'first' => 1,
			'second' => 'two'
		];
		$jobConfig = JobConfig::create([
			'endpoint' => 'ep',
			'params' => $arr
		]);

		$client = new RestClient(new Client);
		$request = $client->createRequest($jobConfig->getConfig());

		$expected = new RestRequest('ep', $arr);

		$this->assertEquals($expected, $request);
	}

	public function testGetGuzzleRequest()
	{
		$client = new RestClient(new Client);
		$requestGet = new RestRequest('ep', ['a' => 1]);
		$requestPost = new RestRequest('ep', ['a' => 1], 'POST');
		$requestForm = new RestRequest('ep', ['a' => 1], 'FORM');

		$get = $this->callMethod($client, 'getGuzzleRequest', [$requestGet]);
		$post = $this->callMethod($client, 'getGuzzleRequest', [$requestPost]);
		$form = $this->callMethod($client, 'getGuzzleRequest', [$requestForm]);

		$this->assertEquals('ep?a=1', $get->getUrl());

		$this->assertEquals('ep', $post->getUrl());
		$this->assertEquals('{"a":1}', $post->getBody());

		$this->assertEquals('ep', $form->getUrl());
		$this->assertEquals(['a' => 1], $form->getBody()->getFields());
	}

	public function testDownload()
	{
		$body = '[
				{"field": "data"},
				{"field": "more"}
		]';

		$guzzle = new Client();
		$guzzle->setDefaultOption('headers', ['X-Test' => '1234']);

		$mock = new Mock([
			new Response(200, [], Stream::factory($body))
		]);
		$guzzle->getEmitter()->attach($mock);

		$history = new History();
		$guzzle->getEmitter()->attach($history);

		$restClient = new RestClient($guzzle);

		$request = new RestRequest('ep', ['a' => 1]);

		$this->assertEquals(json_decode($body), $restClient->download($request));
		$this->assertEquals('ep?a=1', $history->getLastRequest()->getUrl());
		$this->assertEquals('GET', $history->getLastRequest()->getMethod());
		$this->assertEquals(
			[1234],
			$history->getLastRequest()->getHeaders()['X-Test']
		);
	}

	public function testBackoff()
	{
		Logger::setLogger($this->getLogger('test', true));

		$body = '[
				{"field": "data"},
				{"field": "more"}
		]';

		$restClient = RestClient::create();

		$mock = new Mock([
			new Response(429, ['Retry-After' => 1]),
			new Response(200, [], Stream::factory($body))
		]);
		$restClient->getClient()->getEmitter()->attach($mock);

		$history = new History();
		$restClient->getClient()->getEmitter()->attach($history);

		$request = new RestRequest('ep', ['a' => 1]);

		$this->assertEquals(json_decode($body), $restClient->download($request));
	}
}
