<?php

use	Keboola\ExtractorBundle\Tests\Extractor\Jobs\MockJsonJob,
	Keboola\ExtractorBundle\Config\JobConfig,
	Keboola\ExtractorBundle\Common\Logger as KbLog;

use	Keboola\Json\Parser;
use	Keboola\Utils\Utils;
use	Monolog\Logger;
use	Keboola\ExtractorBundle\Exception\UserException;

use	GuzzleHttp\Client;
use	GuzzleHttp\Subscriber\Retry\RetrySubscriber,
	GuzzleHttp\Event\AbstractTransferEvent;

class JsonJobTest extends ExtractorTestCase
{
	public function testParse()
	{
		$parser = new Parser($this->getLogger('jsonJobTest', true));
		$job = $this->getJob($parser);

		$data = '[{"id":1,"name":"Apos"},{"id":2,"name":"Aneg"},{"id":3,"name":"Bpos"},{"id":4,"name":"Bneg"},{"id":5,"name":"Opos"},{"id":6,"name":"Oneg"},{"id":7,"name":"ABpos"},{"id":8,"name":"ABneg"}]';
		$response = Utils::json_decode($data);

		self::callMethod($job, 'parse', [$response]);


		$expectedContents = '"id","name"
"1","Apos"
"2","Aneg"
"3","Bpos"
"4","Bneg"
"5","Opos"
"6","Oneg"
"7","ABpos"
"8","ABneg"
';

		$this->assertEquals(file_get_contents(
			$parser->getCsvFiles()['bloodType']->getPathName()),
			$expectedContents
		);

		$this->assertArrayHasKey('bloodType', $parser->getCsvFiles());
	}

	public function testParseWithParentId()
	{
		$parser = new Parser($this->getLogger('jsonJobTest', true));
		$job = $this->getJob($parser);

		$data = '[{"id":1,"name":"Apos"},{"id":2,"name":"Aneg"},{"id":3,"name":"Bpos"},{"id":4,"name":"Bneg"},{"id":5,"name":"Opos"},{"id":6,"name":"Oneg"},{"id":7,"name":"ABpos"},{"id":8,"name":"ABneg"}]';
		$response = Utils::json_decode($data);

		self::callMethod($job, 'parse', [$response, ['some_id' => 165]]);

		$expectedContents = '"id","name","some_id"
"1","Apos","165"
"2","Aneg","165"
"3","Bpos","165"
"4","Bneg","165"
"5","Opos","165"
"6","Oneg","165"
"7","ABpos","165"
"8","ABneg","165"
';

		$this->assertEquals(
			file_get_contents($parser->getCsvFiles()['bloodType']->getPathName()),
			$expectedContents
		);
	}

	/**
	 * @expectedException Keboola\ExtractorBundle\Exception\UserException
	 * @expectedExceptionMessage More than one array found in response! Use "dataField" column to specify a key to the data array.
	 */
	public function testParseMultiArrayFailure()
	{
		$response = new \stdClass();
		$response->arr1 = ["a","b"];
		$response->arr2 = ["c","d"];
		self::callMethod($this->getJob(), 'parse', [$response]);
	}

	public function testParseNoArray()
	{
		/**
		 * @var \Monolog\Handler\TestHandler()
		 */
		$logHandler = new \Monolog\Handler\TestHandler();
		KbLog::setLogger(new Logger(
			'test-json-job',
			[$logHandler]
		));
		$response = new \stdClass();
		$response->str = "something";
		$response->obj = new \stdClass();
		$response->obj->str = "nested_something";

		$this->assertEquals(self::callMethod($this->getJob(), 'parse', [$response]), []);
		$this->assertEquals($logHandler->hasWarning('No data array found in response!'), true);
	}

	public function testParseArrayInObject()
	{
		$response = new \stdClass();
		$response->str = "something";
		$response->obj = new \stdClass();
		$response->obj->arr = ["will", "ignore", "this"];
		$response->arr = ["asdf", "qwop"];

		$this->assertEquals(self::callMethod($this->getJob(), 'parse', [$response]), $response->arr);
	}

	public function testParseArrayInObjectUsingDataField()
	{
		$response = new \stdClass();
		$response->str = "something";
		$response->obj = new \stdClass();
		$response->obj->arr = ["asdf", "qwop", "arr", "ha"];
		$response->arr = ["will", "ignore", "this", "now"];

		$config = JobConfig::create([
			'rowId' => 'datafieldTest',
			'dataType' => 'random_stuff',
			'dataField' => "obj.arr"
		]);
		$job = new MockJsonJob($config, new Client(), new Parser($this->getLogger('jsonJobTest', true)));

		$this->assertEquals(self::callMethod($job, 'parse', [$response]), $response->obj->arr);
	}

	protected function getJob($parser = null)
	{
		$parser = is_null($parser) ? new Parser($this->getLogger('jsonJobTest', true)) : $parser;

		$config = JobConfig::create([
			'rowId' => 128,
			'dataType' => 'bloodType'
		]);
		return new MockJsonJob($config, new Client(), $parser);
	}
}
