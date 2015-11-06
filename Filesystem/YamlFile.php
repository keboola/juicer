<?php

namespace Keboola\Juicer\Filesystem;

use Symfony\Component\Yaml\Yaml;
use Keboola\Juicer\Exception\ApplicationException,
    Keboola\Juicer\Exception\UserException,
    Keboola\Juicer\Exception\FileNotFoundException,
    Keboola\Juicer\Exception\NoDataException;

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

    /**
     * @param string $path,... Nodes within the Yaml file. Each argument goes one level deeper.
     */
    public function get()
    {
        $path = func_get_args();

        if (is_scalar($this->data) && func_num_args() > 0) {
            throw new NoDataException("Cannot retrieve nested nodes from a scalar in the YAML.");
        }

        // TODO test to ensure a data object isn't changed
        $data = $this->data;
        foreach($path as $key) {
            $data = (array) $data;
            if (!isset($data[$key])) {
                $pathString = join('.', $path);
                throw new NoDataException("Path '{$key}' in '{$pathString}' not found in data!", 0, null, $data);
            }

            $data = $data[$key];
        }

        return $data;
    }

    public function setData($data)
    {
        $this->data = $data;
    }

    public function update($data)
    {
        // FIXME what if it's not an array?
        $this->data = array_replace_recursive($this->data, $data);
    }
}
