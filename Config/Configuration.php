<?php

namespace Keboola\Juicer\Config;

use Symfony\Component\Yaml\Yaml;
use Keboola\Juicer\Exception\ApplicationException,
    Keboola\Juicer\Exception\UserException,
    Keboola\Juicer\Exception\FileNotFoundException,
    Keboola\Juicer\Exception\NoDataException,
    Keboola\Juicer\Filesystem\YamlFile;
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
     * @var array
     */
    protected $ymlConfig = [];

    /**
     * @var string
     */
    protected $dataDir;

    /**
     * @var YamlFile[]
     */
    protected $yamlFiles;

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
            $iterations = $this->getYaml('/config.yml', 'parameters', 'iterations');
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
     * @todo separate the loading of YML and pass it as an argument
     */
    public function getConfig(array $params = null)
    {
        try {
            $configYml = $this->getYaml('/config.yml', 'parameters', 'config');
        } catch(NoDataException $e) {
            throw new UserException($e->getMessage(), 0, $e);
        }

        if (!is_null($params)) {
            $configYml = array_replace($configYml, $params);
        }

        $configName = empty($configYml['id']) ? '' : $configYml['id'];
        $runtimeParams = []; // TODO get runtime params from console

        if (empty($configYml['jobs'])) {
            throw new UserException("No 'jobs' specified in the config!");
        }

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
     * @todo bool $asArray = false
     * @return YamlFile
     */
    public function getMetadata()
    {
        $yaml = new YamlFile($this->dataDir . "/in/state.yml");
        try {
            $yaml->load();
        } catch(FileNotFoundException $e) {
            // log?
        }
        return $yaml;
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
     * @deprecated by getYaml($path, $child1key, $child2key, ...)
     */
    protected function getYmlConfig($path = '/config.yml')
    {
        if (empty($this->ymlConfig[$path])) {
            $this->ymlConfig[$path] = Yaml::parse(file_get_contents($this->dataDir . $path));
        }
        return $this->ymlConfig[$path];
    }

    /**
     * @param string $filePath
     * @param string $path,..
     */
    protected function getYaml($filePath)
    {
        $path = func_get_args();
        $filePath = array_shift($path);

        if (empty($this->yamlFiles[$filePath])) {
            $this->yamlFiles[$filePath] = YamlFile::create($this->dataDir . $filePath);
        }

        return call_user_func_array([$this->yamlFiles[$filePath], 'get'], $path);
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

            file_put_contents($path . $key . '.manifest', Yaml::dump($manifest));
            copy($file->getPathname(), $path . $key);
        }
    }
}
