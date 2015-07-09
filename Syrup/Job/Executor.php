<?php
/**
 * Created by Ondrej Vana <kachna@keboola.com>
 * Date: 17/09/14
 */

namespace Keboola\ExtractorBundle\Syrup\Job;


use	Keboola\ExtractorBundle\Exception\UserException,
	Keboola\ExtractorBundle\Exception\ApplicationException;

use	Monolog\Logger as Monolog,
	Monolog\Registry as MonologRegistry;

use	Keboola\Temp\Temp;
use	Keboola\CsvTable\Table;

use	Keboola\ExtractorBundle\Config\Configuration,
	Keboola\ExtractorBundle\Config\Config,
	Keboola\ExtractorBundle\Extractor\Extractor,
	Keboola\ExtractorBundle\Common\Logger,
	Keboola\ExtractorBundle\Syrup\SapiUploader;

/**
 * Called by Async Syrup with the Job details and initializes the Extractor.
 * @TODO don't extend in EX, use this executor directly in services.yml instead
 * Run should replace this, OR most of run command should be moved here
 */
class Executor
{
	/**
	 * @var Extractor
	 */
	protected $extractor;

	/**
	 * @var Configuration
	 */
	protected $configuration;

	/**
	 * @var Temp
	 */
	protected $temp;

	/**
	 * @param Configuration $configuration
	 * @param Extractor $extractor
	 * @param Monolog $log
	 * @param Temp $temp
	 */
	public function __construct(
		Configuration $configuration,
		Extractor $extractor,
		Monolog $log,
		Temp $temp
	) {
		$this->configuration = $configuration;
		$this->extractor = $extractor;
		Logger::setLogger($log); // TODO: use Monolog/Registry app-wide!
		MonologRegistry::addLogger($log, 'extractor');
		$this->temp = $temp;
	}

	/**
	 * @TODO need to have an option to get OAuth application credentials from Docker
	 */
	public function execute(SyrupJob $job)
	{
		/*
		 * TODO:
		 *	leave the extractor constructor to be used for DI from parameters
		 * 	create some sort of "init" fn in the interface,
		 * 		-run it fromexecutor
		 *		-param should be Common\Config
		 * 	alternatively add an optional "parameters?" to Configuration in services.yml
		 *		and allow it to be set to that parameters.yml value, then pass it through in Config?
		 *  OR!! load "appname" array from parameters.yml by default, and keep it in Config property! <<<<<< this
		 * 	create a "sync" executor to be run from cmd(and tests!),
		 *		that'll be configurable from cmd or tests
		 * 			- load json config from a file?
		 *	create a Command for such executor
		 */
		$this->extractor->setTemp($this->temp); // optional!

		$config = $this->getConfig($job->getParams());
		$config->setRunId($job->getRunId());

		// TODO why not in config?
		$this->extractor->setMetadata($this->configuration->getConfigMetadata($config->getConfigName()));

		$time['start'] = time();

		try {
			Logger::log("INFO", sprintf("Extractor %s started execution using the %s config.", $config->getAppName(), $config->getConfigName()));
			$results = $this->extractor->run($config);
			$this->validateResults($results);
			Logger::log("INFO", sprintf("Extractor %s finished successfully using the %s config.", $config->getAppName(), $config->getConfigName()));

			$this->sapiUpload($config->getConfigName(), $results, $config->getAttributes());
		} catch(\Exception $e) {
			$time['error'] = time();
			// use finally in 5.5 to save this
			$this->saveLastRunTimes($config->getConfigName(), $time);
			throw $e;
		}

		$time['success'] = time();
		$this->saveLastRunTimes($config->getConfigName(), $time);
		$this->configuration->saveConfigMetadata($config->getConfigName(), $this->extractor->getMetadata()); // TODO functional test
	}

// 	/**
// 	 * @param array $params
// 	 * @return Config
// 	 */
// 	protected function getConfig(array $params)
// 	{
// 		try {
// 			$config = $this->configuration->getConfig(
// 				$params,
// 				$this->getConfigBucketName()
// 			);
// 		} catch(ClientException $e) {
// 			throw new UserException($e->getMessage(), 400, $e);
// 		}
//
// 		return $config;
// 	}
//
	/**
	 * Ensure the extractor result is an array of Table
	 * @param array $results
	 */
	protected function validateResults($results)
	{
		if (!is_array($results)) {
			throw new ApplicationException(
				"Extractor::process() should return an array of result CSV files. '" . gettype($results) . "' returned."
			);
		} else {
			foreach($results as $key => $result) {
				if (!($result instanceof Table)) {
					throw new ApplicationException(
						"Extractor::process() should return an array of result CSV files. '" . gettype($result) . "' returned in {$key}."
					);
				}
			}
		}
	}

	/**
	 * Save last success/fail/start to the config table attr
	 * @param array $times
	 * TODO to docker's yml
	 */
	protected function saveLastRunTimes($configName, array $times)
	{
		$configTableName = "sys.c-" . $this->extractor->getFullName() . "." . $configName; // FIXME
		foreach($times as $event => $time) {
			$this->storageApi->setTableAttribute(
				$configTableName,
				"lastRun.{$event}",
				date(DATE_W3C, $time)
			);
		}
	}
}
