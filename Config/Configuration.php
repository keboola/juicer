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

	/**
	 * @var array
	 */
	protected $ymlConfig = [];

	/**
	 * @var string
	 */
	protected $dataDir;

	public function __construct($dataDir, $appName, Temp $temp)
	{
		$this->appName = $appName;
		$this->temp = $temp;
		$this->dataDir = $dataDir;
	}

	/**
	 * @param string $dataDir Path to folder containing config.yml
	 * @return Config
	 */
	public function getConfig()
	{
		$configYml = $this->getYmlConfig()['parameters']['config'];

// 		$configName = $params['config']; // FIXME load from env_var (docker) N/A https://github.com/keboola/docker-bundle/blob/master/ENVIRONMENT.md
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
	 * @return array
	 */
	public function getConfigMetadata()
	{
		if (file_exists($this->dataDir . "/in/state.yml")) {
			return $this->getYmlConfig($this->dataDir, "/in/state.yml");
		} else {
			return null;
		}
	}

	public function saveConfigMetadata(array $data)
	{
		file_put_contents($this->dataDir . "/out/state.yml", Yaml::dump($data));
	}

	protected function getYmlConfig($path = '/config.yml')
	{
		if (empty($this->ymlConfig[$path])) {
			$this->ymlConfig[$path] = Yaml::parse(file_get_contents($this->dataDir . $path));
		}
		return $this->ymlConfig[$path];
	}
}
