<?php

declare(strict_types=1);

namespace Keboola\Juicer\Parser;

use NoRewindIterator;
use Keboola\Csv\CsvReader;
use Keboola\CsvMap\Mapper;
use Keboola\CsvMap\Exception\BadConfigException;
use Keboola\CsvMap\Exception\BadDataException;
use Keboola\CsvTable\Table;
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
    protected array $mappers;

    protected ?ParserInterface $fallback;

    private LoggerInterface $logger;

    public function __construct(Config $config, LoggerInterface $logger, ?ParserInterface $fallbackParser = null)
    {
        if (empty($config->getAttribute('mappings'))) {
            throw new UserException('Cannot initialize JSON Mapper with no mapping');
        }

        $mappers = [];
        foreach ($config->getAttribute('mappings') as $type => $mapping) {
            if (empty($mapping)) {
                throw new UserException(sprintf(
                    "Empty mapping for '%s' data type in 'mappings' config.",
                    $type
                ));
            }

            if (!is_array($mapping)) {
                throw new UserException(sprintf(
                    "Mapping must be 'array' type, '%s' type given, for '%s' data type in 'mappings' config.",
                    gettype($mapping),
                    $type
                ));
            }

            $mappers[$type] = new Mapper($mapping, true, $type);
        }

        foreach ($config->getJobs() as $job) {
            $type = $job->getDataType();
            if (empty($mappers[$type])) {
                if (is_null($fallbackParser)) {
                    throw new UserException(sprintf(
                        "No mapping for '%s' data type in 'mappings' config.",
                        $type
                    ));
                }
            }
        }
        $this->mappers = $mappers;
        $this->logger = $logger;
        $this->fallback = $fallbackParser;
    }

    /**
     * @inheritdoc
     */
    public function process(array $data, $type, $parentId = null): void
    {
        try {
            if (empty($this->mappers[$type])) {
                if (empty($this->fallback)) {
                    throw new UserException("Mapper for type '{$type}' has not been configured.");
                }

                $this->fallback->process($data, $type, (array) $parentId);
                return;
            }

            $this->mappers[$type]->parse($data, (array) $parentId);
        } catch (BadConfigException $e) {
            throw new UserException('Bad Json to CSV Mapping configuration: ' . $e->getMessage(), 0, $e);
        } catch (BadDataException $e) {
            throw new UserException(
                "Error saving '{$type}' data to CSV column: " . $e->getMessage(),
                0,
                $e,
                $e->getData()
            );
        }
    }

    /**
     * @return Table[]
     */
    public function getResults(): array
    {
        $results = [];
        foreach ($this->mappers as $parser) {
            $files = array_filter($parser->getCsvFiles());
            $results = $this->mergeResults($results, $files);
        }

        if (!empty($this->fallback)) {
            $files = array_filter($this->fallback->getResults());
            $results = $this->mergeResults($results, $files);
        }

        return $results;
    }

    /**
     * @param Table[] $results
     * @param Table[] $files
     * @return array
     * @throws UserException
     */
    protected function mergeResults(array $results, array $files): array
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

    protected function mergeFiles(Table $file1, Table $file2): void
    {
        // Create reader for file2 and skip header
        $file2Reader = new NoRewindIterator(new CsvReader($file2->getPathName()));
        $file2Reader->next();

        // Copy content of the file2 to file1
        foreach ($file2Reader as $row) {
            $file1->writeRow($row);
        }
    }

    public function getMappers(): array
    {
        return $this->mappers;
    }

    public function getMetadata(): array
    {
        return empty($this->fallback) ? [] : $this->fallback->getMetadata();
    }
}
