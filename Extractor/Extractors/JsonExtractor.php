<?php

namespace Keboola\ExtractorBundle\Extractor\Extractors;

use	Keboola\Json\Parser as JsonParser;
use	Keboola\ExtractorBundle\Config\Config;
use	Monolog\Registry as Monolog;

/**
 * Prepare and store JsonParser's data.
 * Extends the Extractor by setting **$this->parser** to a **Keboola\Json\Parser** object, initialized with $struct stored in configuration bucket attributes (json.struct, serialized).
 *
 * @TODO: Instead of "extending" the process(), a getParser() should be here - but the run() doesn't have $config["json"] - error in storage of the struct?
 */
abstract class JsonExtractor extends RestExtractor
{
	/**
	 * Initialize JSON parser from a serialized OR decoded structure
	 * @param Config $config
	 * @return JsonParser
	 */
	public function getParser(Config $config = null)
	{
		if (!empty($this->metadata['json_parser.struct']) && is_string($this->metadata['json_parser.struct'])) {
			$struct = unserialize($this->metadata['json_parser.struct']);
		} else {
			$struct = [];
		}

		$rowsToAnalyze = !empty($config->getRuntimeParams()["analyze"]) ? $config->getRuntimeParams()["analyze"] : -1;
		$parser = new JsonParser(Monolog::getInstance('extractor'), $struct, $rowsToAnalyze);
		$parser->setTemp($this->getTemp());
		return $parser;
	}

	protected function updateParserMetadata(JsonParser $parser)
	{
		if ($parser->hasAnalyzed()) {
			$this->metadata['json_parser.struct'] = serialize($parser->getStruct());
		}
	}
}
