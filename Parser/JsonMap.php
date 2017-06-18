<?php

namespace Keboola\Juicer\Parser;

use Keboola\CsvMap\Mapper;
use Keboola\CsvMap\Exception\BadConfigException;
use Keboola\CsvMap\Exception\BadDataException;
use Keboola\Csv\CsvFile;
use Keboola\Juicer\Config\Config;
use Keboola\Juicer\Exception\UserException;
use Psr\Log\LoggerInterface;

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
     * @var ParserInterface
     */
    protected $fallback;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param Mapper[] $mappers
     * @param LoggerInterface $logger
     */
    public function __construct(array $mappers, LoggerInterface $logger)
    {
        $this->mappers = $mappers;
        $this->logger = $logger;
    }

    /**
     * @param Config $config
     * @param LoggerInterface $logger
     * @param ParserInterface|null $fallbackParser
     * @return static
     * @throws UserException
     */
    public static function create(Config $config, LoggerInterface $logger, ParserInterface $fallbackParser = null)
    {
        if (empty($config->getAttribute('mappings'))) {
            throw new UserException("Cannot initialize JSON Mapper with no mapping");
        }

        $mappers = [];
        foreach ($config->getAttribute('mappings') as $type => $mapping) {
            if (empty($mapping)) {
                throw new UserException("Empty mapping for '{$type}' in config.");
            }

            $mappers[$type] = new Mapper($mapping, $type);
        }

        foreach ($config->getJobs() as $job) {
            $type = $job->getDataType();
            if (empty($mappers[$type])) {
                if (is_null($fallbackParser)) {
                    throw new UserException("Missing mapping for '{$type}' in config.");
                }
            }
        }

        $parser = new static($mappers, $logger);
        $parser->setFallbackParser($fallbackParser);
        return $parser;
    }

    /**
     * @inheritdoc
     */
    public function process(array $data, $type, $parentId = null)
    {
        try {
            if (empty($this->mappers[$type])) {
                if (empty($this->fallback)) {
                    throw new UserException("Mapper for type '{$type}' has not been configured.");
                }

                return $this->fallback->process($data, $type, (array) $parentId);
            }

            return $this->mappers[$type]->parse($data, (array) $parentId);
        } catch (BadConfigException $e) {
            throw new UserException("Bad Json to CSV Mapping configuration: " . $e->getMessage(), 0, $e);
        } catch (BadDataException $e) {
            throw new UserException("Error saving '{$type}' data to CSV column: " . $e->getMessage(), 0, $e, $e->getData());
        }
    }

    public function getResults()
    {
        $results = [];
        foreach ($this->mappers as $type => $parser) {
            $files = array_filter($parser->getCsvFiles());
            $results = $this->mergeResults($results, $files);
        }

        if (!empty($this->fallback)) {
            $files = array_filter($this->fallback->getResults());
            $results = $this->mergeResults($results, $files);
        }

        return $results;
    }

    protected function mergeResults(array $results, array $files)
    {
        foreach ($files as $name => $file) {
            if (array_key_exists($name, $results)) {
                $this->logger->debug("Merging results for '{$name}'.");

                $existingHeader = $results[$name]->getHeader();
                $newHeader = $file->getHeader();

                if ($existingHeader !== $newHeader) {
                    throw new UserException(
                        "Multiple results for '{$name}' table have different columns!",
                        0,
                        null,
                        ['differentColumns' => array_diff($existingHeader, $newHeader)]
                    );
                }

                $this->mergeFiles($results[$name], $file);
            } else {
                $results[$name] = $file;
            }
        }

        return $results;
    }

    protected function mergeFiles(CsvFile $file1, CsvFile $file2)
    {
        // CsvFile::getHeader resets it to the first line,
        // so we need to forward it back to the end to append it
        // Also, this is a dirty, dirty hack
        while ($file1->valid()) {
            $file1->next();
        }

        $header = true;
        foreach ($file2 as $row) {
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

    public function setFallbackParser(ParserInterface $fallback = null)
    {
        $this->fallback = $fallback;
    }

    public function getMetadata()
    {
        return empty($this->fallback) ? [] : $this->fallback->getMetadata();
    }
}
