<?php

namespace Keboola\Juicer\Config;

use	Symfony\Component\Yaml\Yaml;
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

		// TODO allow this missing with outputBucket in place
		if (empty($configYml['id'])) {
			throw new UserException("Missing config parameter 'id'!");
		}

		$configName = $configYml['id'];
		$params = []; // TODO get runtime params from console

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
			return $this->getYmlConfig("/in/state.yml");
		} else {
			return null;
		}
	}

	public function saveConfigMetadata(array $data)
	{
		file_put_contents($this->dataDir . "/out/state.yml", Yaml::dump($data));
	}

	/**
	 * @param string $path
	 * @return array
	 * @todo 2nd param to get part of the config with "not found" handling
	 */
	protected function getYmlConfig($path = '/config.yml')
	{
		if (empty($this->ymlConfig[$path])) {
			$this->ymlConfig[$path] = Yaml::parse(file_get_contents($this->dataDir . $path));
		}
		return $this->ymlConfig[$path];
	}

	/**
	 * @return string
	 */
	public function getAppName()
	{
		return $this->appName;
	}

	/**
	 * @param Table[] $csvFiles
	 * @param string $bucketName
	 */
	public function storeResults(array $csvFiles, $bucketName, $sapiPrefix = true)
	{
		$path = $this->dataDir . '/out/tables/';
		$bucketName .= $sapiPrefix ? "in.c-" : "";

		foreach($csvFiles as $key => $file) {
			file_put_contents($path . $key . '.manifest', Yaml::dump([
				'destination' => "in.c-{$bucketName}.{$key}"
			]));
			copy($file->getPathname(), $path . $key);
		}
	}
}
