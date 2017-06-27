<?php

namespace Keboola\Juicer\Config;

use Keboola\Juicer\Exception\UserException;

/**
 * Carries a job configuration
 */
class JobConfig
{
    /**
     * @var string
     */
    protected $jobId;

    /**
     * @var JobConfig[]
     */
    protected $childJobs = [];

    /**
     * @var array
     */
    protected $config;

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
        $this->jobId = $config['id'];
        $this->config = $config;

        if (empty($config['endpoint'])) {
            throw new UserException("The 'endpoint' property must be set in job.", 0, null, [$config]);
        }
        if (!empty($config['children'])) {
            foreach ($config['children'] as $child) {
                if (!is_array($child)) {
                    throw new UserException("Job configuration must be an array: " . var_export($child));
                }
                $child = new JobConfig($child);
                $this->childJobs[$child->getJobId()] = $child;
            }
        }
    }

    /**
     * @return JobConfig[]
     */
    public function getChildJobs() : array
    {
        return $this->childJobs;
    }

    /**
     * @return string
     */
    public function getJobId() : string
    {
        return $this->jobId;
    }

    /**
     * @return array
     */
    public function getConfig() : array
    {
        return $this->config;
    }

    /**
     * @return string
     * @todo should JobConfig store endpoint and params separately?
     */
    public function getEndpoint()
    {
        return $this->config['endpoint'];
    }

    /**
     * @param string $endpoint
     */
    public function setEndpoint($endpoint)
    {
        $this->config['endpoint'] = $endpoint;
    }

    /**
     * @return array
     */
    public function getParams()
    {
        return empty($this->config['params']) ? [] : (array) $this->config['params'];
    }

    /**
     * @param array $params
     */
    public function setParams(array $params)
    {
        $this->config['params'] = $params;
    }

    /**
     * @param string $name
     * @param mixed $value
     */
    public function setParam($name, $value)
    {
        if (!isset($this->config['params'])) {
            $this->config['params'] = [];
        }

        $this->config['params'][$name] = $value;
    }

    /**
     * @return string
     */
    public function getDataType()
    {
        return empty($this->config['dataType']) ? $this->config['endpoint'] : $this->config['dataType'];
    }

    /**
     * @param string $type
     */
    public function setDataType($type)
    {
        $this->config['dataType'] = $type;
    }
}
