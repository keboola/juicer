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
	 * @return Config[]
	 */
	public function getMultipleConfigs()
	{
		if (empty($this->getYmlConfig()['parameters']['iterations'])) {
			$iterations = [null];
		} else {
			$iterations = $this->getYmlConfig()['parameters']['iterations'];
		}

		$configs = [];
		foreach($iterations as $params) {
			$configs[] = $this->getConfig($params);
		}

		return $configs;
	}

	/**
	 * @param array $params Values to override in the config
	 * @return Config
	 * @todo separate the loading of YML and pass it as an argument
	 */
	public function getConfig(array $params = null)
	{
		$configYml = $this->getYmlConfig()['parameters']['config'];

		if (!is_null($params)) {
			$configYml = array_replace($configYml, $params);
		}

		if (empty($configYml['id'])) {
			if (empty($configYml['outputBucket'])) {
				throw new UserException("Missing config parameter 'id' or 'outputBucket'!");
			} else {
				$configYml['id'] = "";
			}
		}

		$configName = $configYml['id'];
		$runtimeParams = []; // TODO get runtime params from console

		$jobs = $configYml['jobs'];
		$jobConfigs = [];
		foreach($jobs as $job) {
			$jobConfig = $this->createJob($job);
			$jobConfigs[$jobConfig->getJobId()] = $jobConfig;
		}
		unset($configYml['jobs']); // weird

		$config = new Config($this->appName, $configName, $runtimeParams);
		$config->setJobs($jobConfigs);
		$config->setAttributes($configYml);

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
		$dirPath = $this->dataDir . '/out';

		if (!is_dir($dirPath)) {
			mkdir($dirPath, 0700, true);
		}

		file_put_contents($dirPath . '/state.yml', Yaml::dump($data));
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
	 * @param bool $sapiPrefix whether to prefix the output bucket with "in.c-"
	 */
	public function storeResults(array $csvFiles, $bucketName, $sapiPrefix = true)
	{
		$path = "{$this->dataDir}/out/tables/{$bucketName}/";
		$bucketName = $sapiPrefix ? 'in.c-' . $bucketName : $bucketName;

		if (!is_dir($path)) {
			mkdir($path, 0755, true);
		}

		foreach($csvFiles as $key => $file) {
			file_put_contents($path . $key . '.manifest', Yaml::dump([
				'destination' => "{$bucketName}.{$key}"
			]));
			copy($file->getPathname(), $path . $key);
		}
	}
}
