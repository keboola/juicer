<?php

use	Keboola\Juicer\Client\RestRequest;

class RestRequestTest extends ExtractorTestCase
{

	public function testCreate()
	{
		$arr = [
			'first' => 1,
			'second' => 'two'
		];
		$request = RestRequest::create([
			'endpoint' => 'ep',
			'params' => $arr
		]);

		$expected = new RestRequest('ep', $arr);

		$this->assertEquals($expected, $request);
	}

}
