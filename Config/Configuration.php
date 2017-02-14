<?php

namespace Keboola\Juicer\Config;

use Keboola\Juicer\Filesystem\JsonFile;
    Keboola\Juicer\Exception\UserException,
    Keboola\Juicer\Exception\FileNotFoundException,
    Keboola\Juicer\Exception\NoDataException,
use Keboola\Temp\Temp;
use Keboola\CsvTable\Table;

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
     * @var string
     */
    protected $dataDir;

    /**
     * @var JsonFile[]
     */
    protected $jsonFiles;

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
        try {
            $iterations = $this->getJSON('/config.json', 'parameters', 'iterations');
        } catch(NoDataException $e) {
            $iterations = [null];
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
     */
    public function getConfig(array $params = null)
    {
        try {
            $configJson = $this->getJSON('/config.json', 'parameters', 'config');
        } catch(NoDataException $e) {
            throw new UserException($e->getMessage(), 0, $e);
        }

        if (!is_null($params)) {
            $configJson = array_replace($configJson, $params);
        }

        $configName = empty($configJson['id']) ? '' : $configJson['id'];
        $runtimeParams = []; // TODO get runtime params from console

        if (empty($configJson['jobs'])) {
            throw new UserException("No 'jobs' specified in the config!");
        }

        $jobs = $configJson['jobs'];
        $jobConfigs = [];
        foreach($jobs as $job) {
            $jobConfig = $this->createJob($job);
            $jobConfigs[$jobConfig->getJobId()] = $jobConfig;
        }
        unset($configJson['jobs']); // weird

        $config = new Config($this->appName, $configName, $runtimeParams);
        $config->setJobs($jobConfigs);
        $config->setAttributes($configJson);

        return $config;
    }

    /**
     * @param object $job
     * @return JobConfig
     */
    protected function createJob($job)
    {
        if (!is_array($job)) {
            throw new UserException("Invalid format for job configuration.", 0, null, ['job' => $job]);
        }

        return JobConfig::create($job);
    }

    /**
     * @return array|null
     * @deprecated by getMetadata
     * @todo once YamlFile defaults to object, override it!
     */
    public function getConfigMetadata()
    {
        return $this->getMetadata()->getData();
    }

    /**
     * @return JsonFile
     */
    public function getMetadata()
    {
        $json = new JsonFile($this->dataDir . "/in/state.json");
        try {
            $json->load();
        } catch(FileNotFoundException $e) {
            // log?
        }
        return $json;
    }

    public function saveConfigMetadata(array $data)
    {
        $dirPath = $this->dataDir . '/out';

        if (!is_dir($dirPath)) {
            mkdir($dirPath, 0700, true);
        }

        file_put_contents($dirPath . '/state.json', json_encode($data));
    }


    /**
     * @param string $filePath
     * @param string $path,..
     */
    protected function getJSON($filePath)
    {
        $path = func_get_args();
        $filePath = array_shift($path);

        if (empty($this->jsonFiles[$filePath])) {
            $this->jsonFiles[$filePath] = JsonFile::create($this->dataDir . $filePath);
        }

        return call_user_func_array([$this->jsonFiles[$filePath], 'get'], $path);
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
     * @param bool $incremental Set the incremental flag in manifest
     */
    public function storeResults(array $csvFiles, $bucketName = null, $sapiPrefix = true, $incremental = false)
    {
        $path = "{$this->dataDir}/out/tables/";

        if (!is_null($bucketName)) {
            $path .= $bucketName . '/';
            $bucketName = $sapiPrefix ? 'in.c-' . $bucketName : $bucketName;
        }

        if (!is_dir($path)) {
            mkdir($path, 0775, true);
            chown($path, fileowner("{$this->dataDir}/out/tables/"));
            chgrp($path, filegroup("{$this->dataDir}/out/tables/"));
        }

        foreach($csvFiles as $key => $file) {
            $manifest = [];

            if (!is_null($bucketName)) {
                $manifest['destination'] = "{$bucketName}.{$key}";
            }

            $manifest['incremental'] = is_null($file->getIncremental())
                ? $incremental
                : $file->getIncremental();

            if (!empty($file->getPrimaryKey())) {
                $manifest['primary_key'] = $file->getPrimaryKey(true);
            }

            file_put_contents($path . $key . '.manifest', json_encode($manifest));
            copy($file->getPathname(), $path . $key);
        }
    }
}
