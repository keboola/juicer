<?php

namespace Keboola\Juicer\Parser;

use	Keboola\Utils\Utils;
use	Keboola\Csv\CsvFile;
use	Keboola\Juicer\Parser\Parser,
	Keboola\Juicer\Common\Logger;

/**
 * Parses Json into a single CSV based on a predefined mapping.
 *
 * Example:
 * - Json:
 * 		{
 * 			"some": "data",
 * 			"object": {
 * 				"more": "moredata"
 * 			},
 * 			"array": [
 * 				"something",
 * 				"something_else"
 * 			]
 * 		}
 *
 * - Mapping:
 * 		{
 * 			"some_col_name": "some",
 * 			"data_from_object": "object/data",
 * 			"first_field_from_array": "array/0"
 * 		}
 * - Result:
 * 		"some_col_name", "data_from_object", "first_field_from_array"
 * 		"data", "moredata", "something"
 *
 * TODO
 * - optional mandatory column (for ID) that has to be set in each $record. Can be generated (either by uid or from other columns or their hash)
 * -
 */
class JsonMap extends Parser
{
	/**
	 * @var array
	 */
	protected $map;

	/**
	 * @param string|array $jsonMap must contain the mapping - for different data "types" (eg. users, posts) a separate instance should be initialized
	 */
	public function __construct($jsonMap) {
		$this->map = Utils::to_assoc($jsonMap);
	}

	/**
	 * Parse a JSON data record into a CSV row
	 * Individual records ("rows" with the JSON) shall be passed to the function
	 *
	 * @param array|object $record
	 * @param CsvFile $csvFile If $csvFile is provided, the resulting row is written into it. The result is returned as an array regardless
	 * @return array Key=>Value pairs of the result
	 */
	public function parse($record, CsvFile $csvFile = null) {
		$result = array();
		foreach($this->map as $key => $path) {
			$field = Utils::getDataFromPath($path, $record);
			if (is_array($field) || is_object($field)) {
				// TODO create a new table OR return json
				// setter function that'll set a handler (parser or a callable function?) for such case?
				// only care about the first field (row) in such table when it comes to columns
				Logger::log("warning", "JSON Parser: Trying to retreive a non-string object in {$key} -> {$path}", $field);
				$result[$key] = json_encode($field);
			} else {
				$result[$key] = $field;
			}
		}

		// if file is set, write the row
		if (!empty($csvFile)) {
			$csvFile->writeRow($result);
		}

		return $result;
	}
}
