<?php

namespace Keboola\Juicer\Extractor;

use    Keboola\Juicer\Exception\UserException;
use    Keboola\Temp\Temp;
use    Keboola\CsvTable\Table;
use    Keboola\Juicer\Config\Config;
use    Monolog\Logger;

/**
 * Base extractor class
 */
abstract class Extractor implements ExtractorInterface
{
    /**
     * Application name, i.e. name of the API (lowercase)
     * @var string
     */
    protected $name = ""; // FIXME not set in 2.0 contained in Config though!
    protected $prefix = "ex";

    /**
     * @var Temp
     */
    protected $temp;

    /**
     * @var Encryptor
     */
    protected $encryptor;

    /**
     * @var array
     */
    protected $metadata = [];

    /**
     * @var Logger
     */
    protected $logger;

    public function __construct(Temp $temp)
    {
        $this->temp = $temp;
    }

    /**
     * Setup the extractor and loop through each job from $config["jobs"] and run the job
     *
     * @param Config $config
     * @return Table[]
     */
    abstract public function run(Config $config);

    /**
     * @return Temp
     */
    protected function getTemp()
    {
        if (empty($this->temp)) {
            $this->temp = new Temp($this->getFullName());
        }

        return $this->temp;
    }

    /**
     * @param Temp $temp
     * @deprecated
     */
    public function setTemp(Temp $temp)
    {
        $this->temp = $temp;
    }

    /**
     * Returns the full name of application (eg. 'ex-dummy')
     * @return string
     */
    public function getFullName()
    {
        return $this->prefix . '-' . $this->name;
    }

    /**
     * @param Encryptor $encryptor
     * @deprecated
     */
    public function setEncryptor(Encryptor $encryptor)
    {
        $this->encryptor = $encryptor;
    }

    public function setMetadata(array $data)
    {
        $this->metadata = $data;
    }

    public function getMetadata()
    {
        return $this->metadata;
    }

    protected function getLogger()
    {
        if (empty($this->logger)) {
            $this->logger = new Logger($this->getFullName());
        }

        return $this->logger;
    }

    public function setLogger(Logger $logger)
    {
        $this->logger = $logger;
    }
}
