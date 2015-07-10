<?php

namespace Keboola\Juicer\Config;

use Symfony\Component\Yaml\Yaml;


use	Keboola\Juicer\Exception\ApplicationException,
	Keboola\Juicer\Exception\UserException;
use	Keboola\Temp\Temp;
use	Keboola\CsvTable\Table;

/**
 *
 */
class Configuration
{
	/**
	 * @var string
	 */
	protected $appName;

	/**
	 * @var Temp
	 */
	protected $temp;

	public function __construct($appName, Temp $temp)
	{
		$this->appName = $appName;
		$this->temp = $temp;
	}

	/**
	 * @param string $dataDir Path to folder containing config.yml
	 * @return Config
	 */
	public function getConfig($dataDir)
	{
		$configYml = Yaml::parse(file_get_contents($dataDir . "/config.yml"))['config'];

// 		$configName = $params['config']; // FIXME load from env_var (docker)
$configName = "test";
$params = []; // FIXME

		$jobs = $configYml['jobs'];
		$jobConfigs = [];
		foreach($jobs as $job) {
			$jobConfig = $this->createJob($job);
			$jobConfigs[$jobConfig->getJobId()] = $jobConfig;
		}
		unset($configYml['jobs']); // weird

		$config = new Config($this->appName, $configName, $params);
		$config->setJobs($jobConfigs);
		$config->setAttributes($configYml);
// 		$config->setMetadata($this->getConfigMetadata($configName));

		return $config;
	}

	/**
	 * @param object $job
	 * @return JobConfig
	 */
	protected function createJob($job)
	{
		return JobConfig::create($job);
	}

	/**
	 * @param string $dataDir
	 * @return array
	 */
	public function getConfigMetadata($dataDir)
	{
		if (file_exists($dataDir . "/state.yml")) {
			return Yaml::parse(file_get_contents($dataDir . "/state.yml"));
		} else {
			return null;
		}
	}

	public function saveConfigMetadata($dataDir, array $data)
	{
		file_put_contents($dataDir . "/state.yml", Yaml::dump($data));
	}
}
