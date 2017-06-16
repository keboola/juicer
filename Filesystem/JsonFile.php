<?php

namespace Keboola\Juicer\Filesystem;

use Keboola\Juicer\Exception\ApplicationException;
use Keboola\Juicer\Exception\FileNotFoundException;
use Keboola\Juicer\Exception\NoDataException;

/**
 * Reflects a JSON file in memory
 */
class JsonFile
{
    const MODE_READ = 'r';
    const MODE_WRITE = 'w';

    /**
     * @var string
     */
    protected $pathName;

    protected $data;

    public function __construct($pathName)
    {
        $this->pathName = $pathName;
    }

    /**
     * @param string $pathName
     * @param string $mode [r,w]
     * @return static
     * @throws ApplicationException
     */
    public static function create($pathName, $mode = self::MODE_READ)
    {
        $json = new self($pathName);

        if ($mode == self::MODE_READ) {
            $json->load();
        } elseif ($mode == self::MODE_WRITE) {
            if (!@touch($pathName)) {
                throw new ApplicationException("Error creating file '{$pathName}'");
            }
            $json->load();
        }

        return $json;
    }

    public function load()
    {
        if (!file_exists($this->pathName)) {
            throw new FileNotFoundException("Failed loading JSON file {$this->pathName}. File does not exist.");
        }

        $this->data = json_decode(file_get_contents($this->pathName), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ApplicationException("Invalid JSON " . json_last_error_msg());
        }
    }

    public function save()
    {
        file_put_contents($this->pathName, json_encode($this->data));
    }

    public function getData()
    {
        return $this->data;
    }

    /**
     * @return array|bool|float|int|mixed|string
     * @throws NoDataException
     */
    public function get()
    {
        $path = func_get_args();

        if (is_scalar($this->data) && func_num_args() > 0) {
            throw new NoDataException("Cannot retrieve nested nodes from a scalar in the JSON.");
        }

        $data = $this->data;
        foreach ($path as $key) {
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
        $this->data = array_replace_recursive($this->data, $data);
    }
}
