<?php

namespace Keboola\Juicer\Parser;

use Keboola\CsvMap\Mapper,
    Keboola\CsvMap\Exception\BadConfigException;
use Keboola\Juicer\Config\Config,
    Keboola\Juicer\Exception\UserException,
    Keboola\Juicer\Exception\ApplicationException,
    Keboola\Juicer\Common\Logger;
use Keboola\Temp\Temp;
use Monolog\Logger as Monolog;

/**
 * Parse JSON results from REST API to CSV
 */
class JsonMap implements ParserInterface
{
    /**
     * @var Mapper[]
     */
    protected $parsers;

    /**
     * @param Mapper[] $mappers
     */
    public function __construct(array $mappers)
    {
        $this->parsers = $mappers;
    }

    public static function create(Config $config) {
        $mappers = [];
        foreach($config->getJobs() as $job) {
            $type = $job->getDataType();
            $jobConfig = $job->getConfig();
//             var_dump($type, $jobConfig);
            if (isset($mappers[$type])) {
                // TODO compare mappings?
            } else {
                if (empty($jobConfig['dataMapping'])) {
                    throw new UserException("Missing 'dataMapping' for '{$type}' in config.");
                }

                $mappers[$type] = new Mapper($jobConfig['dataMapping'], $type);
            }
        }
        return new static($mappers);
    }

    /**
     * Parse the data
     * @param array $data shall be the response body
     * @param string $type data type
     */
    public function process(array $data, $type, $parentId = null)
    {
        try {
            return $this->parsers[$type]->parse($data);
        } catch(BadConfigException $e) {
            throw new UserException("Bad Json to CSV Mapping configuration: " . $e->getMessage(), 0, $e);
        }
    }

    public function getResults()
    {
        $results = [];
        foreach($this->parsers as $type => $parser) {
            $files = array_filter($parser->getCsvFiles());
            $results += $files;
        }

        return $results;
    }
}
