<?php

namespace Keboola\Juicer\Parser;

use Keboola\CsvMap\Mapper,
    Keboola\CsvMap\Exception\BadConfigException,
    Keboola\CsvMap\Exception\BadDataException;
use Keboola\Csv\CsvFile;
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
    protected $mappers;

    /**
     * @param Mapper[] $mappers
     */
    public function __construct(array $mappers)
    {
        $this->mappers = $mappers;
    }

    /**
     * @param Config $config
     * @return static
     */
    public static function create(Config $config) {
        if (empty($config->getAttribute('mappings'))) {
            throw new UserException("Cannot initialize JSON Mapper with no mapping");
        }

        $mappers = [];
        foreach($config->getAttribute('mappings') as $type => $mapping) {
            if (empty($mapping)) {
                throw new UserException("Empty mapping for '{$type}' in config.");
            }

            $mappers[$type] = new Mapper($mapping, $type);
        }

        foreach($config->getJobs() as $job) {
            $type = $job->getDataType();
            if (empty($mappers[$type])) {
                throw new UserException("Missing mapping for '{$type}' in config.");
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
            if (empty($this->mappers[$type])) {
                throw new UserException("Mapper for type '{$type}' has not been configured.");
            }

            return $this->mappers[$type]->parse($data, (array) $parentId);
        } catch(BadConfigException $e) {
            throw new UserException("Bad Json to CSV Mapping configuration: " . $e->getMessage(), 0, $e);
        } catch(BadDataException $e) {
            throw new UserException("Error saving '{$type}' data to CSV column: " . $e->getMessage(), 0, $e, $e->getData());
        }
    }

    public function getResults()
    {
        $results = [];
        foreach($this->mappers as $type => $parser) {
            $files = array_filter($parser->getCsvFiles());
            foreach($files as $name => $file) {
                if (array_key_exists($name, $results)) {
                    Logger::log('debug', "Merging results for '{$name}'.");

                    $existingHeader = $results[$name]->getHeader();
                    $newHeader = $file->getHeader();

                    if ($existingHeader !== $newHeader) {
                        throw new UserException("Multiple results for '{$name}' table have different columns!", 0, null, ['differentColumns' => array_diff($existingHeader, $newHeader)]);
                    }

                    $this->mergeResults($results[$name], $file);
                } else {
                    $results[$name] = $file;
                }
            }

            // Preserves existing keys in array on dupes
            $results += $files;
        }

        return $results;
    }

    protected function mergeResults(CsvFile $file1, CsvFile $file2)
    {
        // CsvFile::getHeader resets it to the first line,
        // so we need to forward it back to the end to append it
        // Also, this is a dirty, dirty hack
        for(;$file1->valid();$file1->next()) {}

        $header = true;
        foreach($file2 as $row) {
            if ($header) {
                $header = false;
                continue;
            }
            $file1->writeRow($row);
        }
    }

    public function getMappers()
    {
        return $this->mappers;
    }
}
