<?php

namespace Keboola\ExtractorBundle\Config;

/**
 * Carries the extractor configuration
 */
class Config
{
	/**
	 * @var string
	 */
	protected $appName;

	/**
	 * @var string
	 */
	protected $configName;

	/**
	 * @var string
	 */
	protected $runId = null;

	/**
	 * @var array
	 */
	protected $attributes = [];

// 	/**
// 	 * Use to carry data between sessions
// 	 * @var array
// 	 */
// 	protected $metadata = [];
//
	/**
	 * @var array
	 */
	protected $runtimeParams = [];

	/**
	 * @var JobConfig[]
	 */
	protected $jobs = [];

	public function __construct($appName, $configName, array $runtimeParams)
	{
		$this->appName = $appName;
		$this->configName = $configName;
		$this->runtimeParams = $runtimeParams;
	}

	public function setRunId($runId)
	{
		$this->runId = $runId;
	}

	public function getRunId()
	{
		return $this->runId;
	}

	public function setAttributes($attributes)
	{
		$this->attributes = $attributes;
	}

	public function getAttributes()
	{
		return $this->attributes;
	}

	public function setJobs($jobs)
	{
		$this->jobs = $jobs;
	}

	public function getJobs()
	{
		return $this->jobs;
	}

	public function getAppName()
	{
		return $this->appName;
	}

	public function getConfigName()
	{
		return $this->configName;
	}

// 	public function setMetadata(array $data)
// 	{
// 		$this->metadata = $data;
// 	}
//
// 	public function updateMetadata(array $data)
// 	{
// 		$this->metadata = array_replace($this->metadata, $data);
// 	}
//
// 	public function getMetadata()
// 	{
// 		return $this->metadata;
// 	}
//
	public function getRuntimeParams()
	{
		return $this->runtimeParams;
	}
}
