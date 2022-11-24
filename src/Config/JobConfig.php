<?php

declare(strict_types=1);

namespace Keboola\Juicer\Config;

use Keboola\Juicer\Exception\UserException;

/**
 * Carries a job configuration
 */
class JobConfig
{
    protected string $jobId;

    /**
     * @var JobConfig[]
     */
    protected array $childJobs = [];

    protected array $config;

    /**
     * Create an instance of Job configuration from configuration associative array
     * @param array $config
     *     example:
     *         [
     *            'id' => 'id',
     *            'endpoint' => ...
     *        ]
     * @throws UserException
     */
    public function __construct(array $config)
    {
        if (empty($config['id'])) {
            $config['id'] = md5(serialize($config));
        }
        if (empty($config['params'])) {
            $config['params'] = [];
        }
        if (!is_array($config['params'])) {
            throw new UserException("The 'params' property must be an array.", 0, null, $config);
        }
        if (!isset($config['endpoint']) || $config['endpoint'] === '') {
            throw new UserException("The 'endpoint' property must be set in job.", 0, null, $config);
        }
        if (empty($config['dataType'])) {
            $config['dataType'] = $config['endpoint'];
        }

        $this->jobId = $config['id'];
        $this->config = $config;

        if (!empty($config['children'])) {
            if (!is_array($config['children'])) {
                throw new UserException("The 'children' property must an array of jobs.", 0, null, $config);
            }
            foreach ($config['children'] as $child) {
                if (!is_array($child)) {
                    throw new UserException('Job configuration must be an array: ' . var_export($child, true));
                }
                $child = new JobConfig($child);
                $this->childJobs[$child->getJobId()] = $child;
            }
        }
    }

    /**
     * @return JobConfig[]
     */
    public function getChildJobs(): array
    {
        return $this->childJobs;
    }

    public function getJobId(): string
    {
        return $this->jobId;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function getEndpoint(): string
    {
        return $this->config['endpoint'];
    }

    public function setEndpoint(string $endpoint): void
    {
        $this->config['endpoint'] = $endpoint;
    }

    public function getParams(): array
    {
        return $this->config['params'];
    }

    public function setParams(array $params): void
    {
        $this->config['params'] = $params;
    }

    /**
     * @param mixed $value
     */
    public function setParam(string $name, $value): void
    {
        $this->config['params'][$name] = $value;
    }

    public function getDataType(): string
    {
        return $this->config['dataType'];
    }
}
