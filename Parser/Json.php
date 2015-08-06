<?php

namespace Keboola\Juicer\Parser;

use	Keboola\Juicer\Parser\Parser;
use	Keboola\Json\Parser as JsonParser;

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
}
