<?php

use	Keboola\ExtractorBundle\Parser\JsonMap;
use	Keboola\Csv\CsvFile;

class JsonMapTest extends ExtractorTestCase {
	private $parser;
	private $expected;

	public function __construct() {
		$map = '{
			"id": "id",
			"text_field": "fields/text",
			"another_field": "fields/path/here"
		}';

		$this->parser = new JsonMap($map);

		$this->expected = array (
			'id' => 1,
			'text_field' => 'Here comes some text!',
			'another_field' => 'some data...',
		);
	}

	public function testParse() {
		$records = array(
			"array" => array(
				"id" => 1,
				"fields" => array(
					"text" => "Here comes some text!",
					"path" => array(
						"here" => "some data..."
					)
				)
			),
// 			"json" => '{ // OBSOLETE, parser should always receive decoded data
// 				"id": 1,
// 				"fields": {
// 					"text": "Here comes some text!",
// 					"path": {
// 						"here": "some data..."
// 					}
// 				}
// 			}',
			"object" => new stdClass
		);
		$records["object"]->id = 1;
		$records["object"]->fields = array(
			"text" => "Here comes some text!",
			"path" => new stdClass
		);
		$records["object"]->fields["path"]->here = "some data...";

		foreach($records as $record) {
			$result = $this->parser->parse($record);
			$this->assertEquals($this->expected, $result);
		}

		// test parse to a file!
		$path = "/tmp/ex-bundle_jsonMap_" . uniqid();
		$file = new CsvFile($path);

		$this->parser->parse($records["array"], $file);

		$file->rewind();
		$this->assertEquals(array_values($this->parser->parse($records["array"])), $file->current());
		unlink($path);
	}

	public function testParseIncomplete() {
		$partial = '{
			"id": 1,
			"fields": {
				"text": "Here comes some text!"
			}
		}';
		$this->assertEquals(array_keys($this->expected), array_keys($this->parser->parse($partial)));

		$noId = array(
			"fields" => array(
				"text" => "Here comes some text!"
			)
		);
		$this->assertEquals(array_keys($this->expected), array_keys($this->parser->parse($noId)));
	}
}
