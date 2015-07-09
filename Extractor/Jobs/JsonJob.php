<?php

namespace Keboola\ExtractorBundle\Extractor\Jobs;

use	Keboola\Json\Parser;
use	Keboola\Utils\Utils;
use	Keboola\ExtractorBundle\Common\Logger;

/**
 * {@inheritdoc}
 * Uses Keboola\Json\Parser as $parser
 * Expects the config table to contain following columns
 * "dataType": optional, type of data in the response
 * "dataField": optional, to override array lookup within the response
 */
abstract class JsonJob extends RestJob
{
	/**
	 * @var Parser
	 */
	protected $parser;

	/**
	 * Try to find the data array in response and parse it
	 * @param mixed $response
	 * @param array|string $parentId ID (or list thereof) to be passed to parser
	 * @return array The data containing array part of response
	 */
	protected function parse($response, $parentId = null)
	{
		$type = !empty($this->config['dataType'])
			? $this->config['dataType']
			: $this->config['endpoint']; // FIXME determine dataType

		$data = $this->findDataInResponse($response, $this->config);
		$this->parser->process($data, $type, $parentId);

		return $data;
	}
}
