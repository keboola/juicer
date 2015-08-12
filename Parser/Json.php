<?php

namespace Keboola\Juicer\Parser;

use	Keboola\Json\Parser as JsonParser;
use	Keboola\Juicer\Config\Config;
use	Keboola\Temp\Temp;
use	Monolog\Logger;

/**
 * Parse XML results from SOAP API to CSV
 */
class Json implements ParserInterface
{
	/**
	 * @var JsonParser
	 */
	protected $parser;

	/**
	 * @param JsonParser $parser
	 */
	public function __construct(JsonParser $parser) {
		$this->parser = $parser;
	}

	/**
	 * Parse the data
	 * @param array $data shall be the response body
	 * @param string $type is a WSDL data type (has to be obtained from the WSDL definition)
	 * @todo Ensure the SOAP client returns an array, and cast it THERE if it doesn't
	 */
	public function process(array $data, $type, $parentId = null)
	{
		$this->parser->process($data, $type, $parentId);
	}

	/**
	 * Return the results list
	 * @return Table[]
	 */
	public function getResults() {
		return $this->parser->getCsvFiles();
	}

	/**
	 * @return JsonParser
	 */
	public function getParser()
	{
		return $this->parser;
	}

	/**
	 * @param Config $config // not used anywhere in real aps (yet? - analyze)
	 * @param Logger $logger
	 * @param Temp $temp
	 * @param array $metadata
	 * @return static
	 */
	public static function create(Config $config, Logger $logger, Temp $temp, array $metadata = [])
	{
		if (!empty($metadata['json_parser.struct']) && is_array($metadata['json_parser.struct'])) {
			$struct = $metadata['json_parser.struct'];
		} else {
			$struct = [];
		}

		$rowsToAnalyze = null != $config && !empty($config->getRuntimeParams()["analyze"]) ? $config->getRuntimeParams()["analyze"] : -1;
		$parser = new JsonParser($logger, $struct, $rowsToAnalyze);
		$parser->setTemp($temp);
		return new static($parser);
	}

	/**
	 * @return array
	 */
	public function getMetadata()
	{
		return ['json_parser.struct' => $this->parser->getStruct()];
	}
}
