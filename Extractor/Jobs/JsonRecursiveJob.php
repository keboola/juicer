<?php

namespace Keboola\Juicer\Extractor\Jobs;

use	Keboola\Juicer\Config\JobConfig,
	Keboola\Juicer\Common\Logger;
use	Keboola\Filter\Filter;
use	Keboola\Utils\Utils;
use	Keboola\Juicer\Exception\UserException;
/**
 * {@inheritdoc}
 * Adds a capability to process recursive calls based on
 * responses.
 * If a endpoint column in config table contains {} enclosed
 * parameter, it'll be replaced by a value from a parent call
 * based on the values from its response and "mapping" set in
 * "recursive"->params column
 * Expects the configuration to use 'endpoint' column to store
 * the API endpoint
 * @todo Separate from JsonJob using an interface to get the
 * 		job and pass to the recursion
 */
abstract class JsonRecursiveJob extends JsonJob implements RecursiveJobInterface
{
	/** @var JobConfig[] */
	protected $childJobs = [];

// 	/**
// 	 * @var array
// 	 * @obsolete
// 	 */
// 	protected $parentParams = [];

// 	/** @var mixed */
// 	protected $parentResult = [];

	/**
	 * @var array
	 */
	protected $parentResults = [];

	/**
	 * {@inheritdoc}
	 */
	public function __construct(JobConfig $config, $client, $parser = null)
	{
		$this->childJobs = $config->getChildJobs();

		parent::__construct($config, $client, $parser);
		// If no dataType is set, save endpoint as dataType before replacing placeholders
		if (empty($this->config['dataType']) && !empty($this->config['endpoint'])) {
			$this->config['dataType'] = $this->config['endpoint'];
		}
	}

	/**
	 * Add parameters from parent call to the Endpoint.
	 * The parameter name in the config's endpoint has to be enclosed in {}
	 * @param array $params
	 */
	public function setParams(array $params)
	{
		foreach($params as $param) {
			$this->config['endpoint'] = str_replace('{' . $param['placeholder'] . '}', $param['value'], $this->config['endpoint']);
		}

		$this->parentParams = $params;
	}

// 	/**
// 	 * @param mixed $result
// 	 * @param mixed $previousParent
// 	 */
// 	public function setParentResult($result, array $previousParent)
// 	{
// 		$previous = [];
// 		foreach($previousParent as $key => $value) {
// 			$parentKey = $this->prependParent($key);
// 			$previous[$parentKey] = $value;
// 		}
//
// 		$this->parentResult = array_replace($previous, (array) $result);
// 	}

	/**
	 * @param string $string
	 * @return string
	 */
	protected function prependParent($string)
	{
		return (substr($string, 0, 7) == "parent_") ? $string : "parent_{$string}";
	}

	/**
	 * {@inheritdoc}
	 * Create subsequent jobs for recursive endpoints
	 * Uses "recursive" column of the config table
	 * @param mixed $response
	 * @return array
	 */
	protected function parse($response)
	{
		// Add parent values to the result
		$parentCols = [];
		foreach($this->parentParams as $k => $v) {
			$k = $this->prependParent($k);
			$parentCols[$k] = $v['value'];
		}

		$data = parent::parse($response, $parentCols);

		/**
		 * @todo separate from JsonJob
		 * @param array $data The response data
		 * @param array $childJobs $this->childJobs
		 * @param // parentParams
		 * @param // parentResult
		 * @param // parentPrefix (?) to prefix & substr from $parentField
		 * 				...OR set it & prependParent() method to go with it
		 * @param string className - needs a better solution! callback?
		 */
		foreach($this->childJobs as $jobId => $child) {
			if (!empty($child->getConfig()['recursionFilter'])) {
				$filter = Filter::create($child->getConfig()['recursionFilter']);
			}

			foreach($data as $result) {
				if (!empty($filter) && ($filter->compareObject((object) $result) == false)){
					continue;
				}

				// TODO add result to the beginning of an array, containing all parent results
				array_unshift($this->parentResults, $result);
				$childJob = $this->createChild($child);

				$childJob->run();
			}
		}

		return $data;
	}

	/**
	 * Create a child job with current client and parser
	 * @param JobConfig $config
	 * @return static
	 */
	protected function createChild(JobConfig $config)
	{
		$job = new static($config, $this->client, $this->parser);

		$params = [];
		foreach($config->getConfig()['placeholders'] as $placeholder => $field) {
// 			if (substr($field, 0, 7) == "parent_") {
// 				$parentField = substr($field, 7);
//
// 				if (!empty($this->parentResult[$parentField])) {
// 					// Search in parent result
// 					// MUST be string TODO
// 					$value = $this->parentResult[$parentField];
// 				} else {
// 					$e = new UserException("{$field} used by {$placeholder} was not set in the parent configuration, nor found in the parent response.");
// 					$e->setData(['parent_data' => $this->parentResult]);
// 					throw $e;
// 				}
// 			} else {
// 				$value = Utils::getDataFromPath($field, $result, ".");
// 				if (empty($value)) {
// 					// Throw an UserException instead?
// 					Logger::log(
// 						"WARNING", "No value found for {$placeholder} in parent result.",
// 						[
// 							'result' => $result,
// // 							'response' => $response,
// // 							'data' => $data
// 						]
// 					);
// 				}
// 			}

			if (strpos($placeholder, ':') !== false) {
				list($level, $ph) = explode(':', $placeholder, 2);
			} else {
				$ph = $placeholder;
				$level = 0;
			}

			$value = Utils::getDataFromPath($field, $this->parentResults[$level], ".");
			if (empty($value)) {
				// Throw an UserException instead?
				Logger::log(
					"WARNING", "No value found for {$placeholder} in parent result.",
					[
						'result' => $this->parentResults,
// 							'response' => $response,
// 							'data' => $data
					]
				);
			}

			$params[$field] = [
				'placeholder' => $placeholder,
				'field' => $field,
				'value' => $value
			];
		}

		// add parent params as well
// 		if (!empty($this->parentParams)) {
// 			$params = array_replace($this->parentParams, $params);
// 		}
//
		$job->setParams($params);
		$job->setParentResults($this->parentResults);

		return $job;
	}

	public function setParentResults(array $results)
	{
		$this->parentResults = $results;
	}
}
