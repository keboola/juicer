<?php

namespace Keboola\Juicer\Filesystem;

use Symfony\Component\Yaml\Yaml;
use Keboola\Juicer\Exception\ApplicationException,
    Keboola\Juicer\Exception\UserException,
    Keboola\Juicer\Exception\FileNotFoundException;

/**
 * Reflects a YAML file in memory
 * @todo try $objectSupport (Yaml::parse() 3rd param) to get an object?
 *  should probably default to object
 * @todo also 2nd param, exceptionOnInvalidType
 */
class YamlFile
{
    /**
     * @var string
     */
    protected $pathName;

    protected $data;

    public function __construct($pathName)
    {
        $this->pathName = $pathName;
    }

    public function load()
    {
        if (!file_exists($this->pathName)) {
            throw new FileNotFoundException("Failed loading YAML file {$this->pathName}. File does not exist.");
        }

        $this->data = Yaml::parse(file_get_contents($this->pathName));
    }

    public function save()
    {
        file_put_contents($this->pathName, Yaml::dump($this->data));
    }

    public function getData()
    {
        return $this->data;
    }

    public function setData($data)
    {
        $this->data = $data;
    }
}
