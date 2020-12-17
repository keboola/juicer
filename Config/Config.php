<?php

declare(strict_types=1);

namespace Keboola\Juicer\Config;

use Keboola\Juicer\Exception\UserException;

/**
 * Carries the extractor configuration
 */
class Config
{
    private array $attributes = [];

    /**
     * @var JobConfig[]
     */
    private array $jobs = [];

    public function __construct(array $configuration)
    {
        if (empty($configuration['jobs']) || !is_array($configuration['jobs'])) {
            throw new UserException("The 'jobs' section is required in the configuration.");
        }

        $jobConfigs = [];
        foreach ($configuration['jobs'] as $job) {
            if (!is_array($job)) {
                throw new UserException('Job configuration must be an array: ' . var_export($job, true));
            }
            $jobConfig = new JobConfig($job);
            $jobConfigs[$jobConfig->getJobId()] = $jobConfig;
        }
        $this->jobs = $jobConfigs;
        unset($configuration['jobs']);
        $this->attributes = $configuration;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * @param string $name
     * @return bool|mixed
     */
    public function getAttribute(string $name)
    {
        return empty($this->attributes[$name]) ? false : $this->attributes[$name];
    }

    /**
     * @return JobConfig[]
     */
    public function getJobs(): array
    {
        return $this->jobs;
    }
}
