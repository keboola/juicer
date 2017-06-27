<?php

namespace Keboola\Juicer\Config;

use Keboola\Juicer\Exception\UserException;

/**
 * Carries the extractor configuration
 */
class Config
{
    /**
     * @var string
     */
    private $configName;

    /**
     * @var string
     */
    private $runId = null;

    /**
     * @var array
     */
    private $attributes = [];

    /**
     * @var JobConfig[]
     */
    private $jobs = [];

    /**
     * Config constructor.
     * @param string $configName
     * @param array $configuration
     * @throws UserException
     */
    public function __construct(string $configName, array $configuration)
    {
        if (empty($configuration['jobs']) || !is_array($configuration['jobs'])) {
            throw new UserException("The 'jobs' section is required in the configuration.");
        }

        $this->configName = $configName;
        $jobConfigs = [];
        foreach ($configuration['jobs'] as $job) {
            if (!is_array($job)) {
                throw new UserException("Job configuration must be an array: " . var_export($job));
            }
            $jobConfig = new JobConfig($job);
            $jobConfigs[$jobConfig->getJobId()] = $jobConfig;
        }
        $this->setJobs($jobConfigs);
        unset($configuration['jobs']);
        $this->setAttributes($configuration);
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
}
