<?php

namespace Keboola\Juicer\Extractor;

use	Keboola\Juicer\Config\JobConfig,
	Keboola\Juicer\Common\Logger,
	Keboola\Juicer\Client\ClientInterface,
	Keboola\Juicer\Parser\ParserInterface;
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
class RecursiveJob extends Job implements Jobs\RecursiveJobInterface
{
	/** @var JobConfig[] */
	protected $childJobs = [];

	/**
	 * Used to save necessary parents' data to child's output
	 * @var array
	 */
	protected $parentParams = [];

	/**
	 * @var array
	 */
	protected $parentResults = [];

	/**
	 * {@inheritdoc}
	 */
	public function __construct(JobConfig $config, ClientInterface $client, ParserInterface $parser)
	{
		$this->childJobs = $config->getChildJobs();

		parent::__construct($config, $client, $parser);
		// If no dataType is set, save endpoint as dataType before replacing placeholders
		if (empty($this->config->getConfig()['dataType']) && !empty($this->config->getConfig()['endpoint'])) {
			$this->config->getConfig()['dataType'] = $this->getDataType();
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
			$this->config->setEndpoint(str_replace('{' . $param['placeholder'] . '}', $param['value'], $this->config->getConfig()['endpoint']));
		}

		$this->parentParams = $params;
	}

	/**
	 * @param string $string
	 * @return string
	 */
	protected function prependParent($string)
	{
		return (substr($string, 0, 7) == "parent_") ? $string : "parent_{$string}";
	}

	/**
	 * Create subsequent jobs for recursive endpoints
	 * Uses "children" section of the job config
	 * {@inheritdoc}
	 */
	protected function parse($response, $parentId = null)
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
		 */
		foreach($this->childJobs as $jobId => $child) {
			if (!empty($child->getConfig()['recursionFilter'])) {
				$filter = Filter::create($child->getConfig()['recursionFilter']);
			}

			foreach($data as $result) {
				if (!empty($filter) && ($filter->compareObject((object) $result) == false)){
					continue;
				}

				// Add current result to the beginning of an array, containing all parent results
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
		// Clone the config to prevent overwriting the placeholder(s) in endpoint
		$job = new static(clone $config, $this->client, $this->parser);

		$params = [];
		$placeholders = !empty($config->getConfig()['placeholders']) ? $config->getConfig()['placeholders'] : [];
		if (empty($placeholders)) {
			Logger::log("WARNING", "No 'placeholders' set for '" . $config->getConfig()['endpoint'] . "'");
		}

		foreach($placeholders as $placeholder => $field) {
			// TODO allow using a descriptive ID by storing the result by `task(job) id` in $parentResults
			if (strpos($placeholder, ':') !== false) {
				list($level, $ph) = explode(':', $placeholder, 2);
				// Make the direct parent a 1 instead of 0 for a better user friendship
				$level -= 1;
			} else {
				$ph = $placeholder;
				$level = 0;
			}

			$value = Utils::getDataFromPath($field, $this->parentResults[$level], ".");
			if (empty($value)) {
				// Throw an UserException instead?
				Logger::log(
					"WARNING", "No value found for {$placeholder} in parent result.",
					['result' => $this->parentResults]
				);
			}

			$params[$field] = [
				'placeholder' => $placeholder,
				'field' => $field,
				'value' => $value
			];
		}

		// Add parent params as well
		if (!empty($this->parentParams)) {
			$params = array_replace($this->parentParams, $params);
		}

		$job->setParams($params);
		$job->setParentResults($this->parentResults);

		return $job;
	}

	public function setParentResults(array $results)
	{
		$this->parentResults = $results;
	}
}
