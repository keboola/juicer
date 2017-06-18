<?php

namespace Keboola\Juicer\Config;

/**
 * Carries the extractor configuration
 */
class Config
{
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

    /**
     * @var array
     */
    protected $runtimeParams = [];

    /**
     * @var JobConfig[]
     */
    protected $jobs = [];

    public function __construct($configName, array $runtimeParams)
    {
        $this->configName = $configName;
        $this->runtimeParams = $runtimeParams;
    }

    /**
     * @param string $runId
     */
    public function setRunId($runId)
    {
        $this->runId = $runId;
    }

    /**
     * @return string
     */
    public function getRunId()
    {
        return $this->runId;
    }

    /**
     * @param array $attributes
     */
    public function setAttributes(array $attributes)
    {
        $this->attributes = $attributes;
    }

    /**
     * @return array
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * @param string $name
     * @return bool|mixed
     */
    public function getAttribute($name)
    {
        return empty($this->attributes[$name]) ? false : $this->attributes[$name];
    }

    /**
     * @param JobConfig[] $jobs
     */
    public function setJobs(array $jobs)
    {
        $this->jobs = $jobs;
    }

    /**
     * @return JobConfig[]
     */
    public function getJobs()
    {
        return $this->jobs;
    }

    /**
     * @return string
     */
    public function getConfigName()
    {
        return $this->configName;
    }

    /**
     * @return array
     */
    public function getRuntimeParams()
    {
        return $this->runtimeParams;
    }
}
