<?php

namespace Keboola\Juicer\Parser;

use	Keboola\Json\Parser as JsonParser,
	Keboola\Json\Exception\JsonParserException,
	Keboola\Json\Struct;
use	Keboola\Juicer\Config\Config,
	Keboola\Juicer\Exception\UserException,
	Keboola\Juicer\Exception\ApplicationException;
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
		try {
			$this->parser->process($data, $type, $parentId);
		} catch(JsonParserException $e) {
			throw new UserException(
				"Error parsing response JSON: " . $e->getMessage(),
				500,
				$e,
				$e->getData()
			);
		}
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
		// TODO move this if to $this->validateStruct altogether
		if (!empty($metadata['json_parser.struct']) && is_array($metadata['json_parser.struct'])) {
			if (
				empty($metadata['json_parser.structVersion'])
				|| $metadata['json_parser.structVersion'] != Struct::STRUCT_VERSION
			) {
				// temporary
				$metadata['json_parser.struct'] = self::updateStruct($metadata['json_parser.struct']);
			}

			$struct = $metadata['json_parser.struct'];
		} else {
			$struct = [];
		}

		$rowsToAnalyze = null != $config && !empty($config->getRuntimeParams()["analyze"]) ? $config->getRuntimeParams()["analyze"] : -1;
		$parser = JsonParser::create($logger, $struct, $rowsToAnalyze);
		$parser->setTemp($temp);
		return new static($parser);
	}

	/**
	 * @return array
	 */
	public function getMetadata()
	{
		return [
			'json_parser.struct' => $this->parser->getStruct(),
			'json_parser.structVersion' => $this->parser->getStructVersion()
		];
	}

	protected static function updateStruct(array $struct)
	{
		foreach($struct as $type => &$children) {
			if (!is_array($children)) {
				throw new ApplicationException("Error updating struct at '{$type}', an array was expected");
			}

			foreach($children as $child => &$dataType) {
				if (in_array($dataType, ['integer', 'double', 'string', 'boolean'])) {
					// Make scalars non-strict
					$dataType = 'scalar';
				} elseif ($dataType == 'array') {
					// Determine array types
					if (!empty($struct["{$type}.{$child}"])) {
						$childType = $struct["{$type}.{$child}"];
						if (array_keys($childType) == ['data']) {
							$dataType = 'arrayOfscalar';
						} else {
							$dataType = 'arrayOfobject';
						}
					}
				}
			}
		}

		return $struct;
	}
}
