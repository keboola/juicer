<?php

declare(strict_types=1);

namespace Keboola\Juicer\Parser;

use Keboola\CsvTable\Table;
use Keboola\Json\Analyzer;
use Keboola\Json\Exception\JsonParserException;
use Keboola\Json\Exception\NoDataException;
use Keboola\Json\Parser;
use Keboola\Json\Structure;
use Keboola\Juicer\Exception\UserException;
use KeboolaLegacy\Json\Analyzer as LegacyAnalyzer;
use KeboolaLegacy\Json\Parser as LegacyParser;
use KeboolaLegacy\Json\Struct;
use Psr\Log\LoggerInterface;

/**
 * Parse JSON results from REST API to CSV
 */
class Json implements ParserInterface
{
    public const LEGACY_VERSION = 2;
    public const LATEST_VERSION = 3;

    /**
     * @var LegacyParser|Parser
     */
    protected $parser;

    protected LoggerInterface $logger;

    public function __construct(
        LoggerInterface $logger,
        array $metadata,
        int $compatLevel,
        int $cacheMemoryLimit = 2000000
    ) {
        $this->logger = $logger;
        if (!empty($metadata['json_parser.struct']) && is_array($metadata['json_parser.struct']) &&
            !empty($metadata['json_parser.structVersion'])) {
            if ($metadata['json_parser.structVersion'] === self::LEGACY_VERSION) {
                $logger->warning('Using legacy JSON parser, because it is in configuration state.');
                $structure = new Struct($logger);
                $structure->load($metadata['json_parser.struct']);
                $structure->setAutoUpgradeToArray(true);
                $analyzer = new LegacyAnalyzer($logger, $structure, -1);
                $analyzer->setNestedArrayAsJson(true);
                $this->parser = new LegacyParser($logger, $analyzer, $structure);
            } else {
                if ($compatLevel !== self::LATEST_VERSION) {
                    $logger->warning(
                        'Ignored request for legacy JSON parser, because configuration is already upgraded.'
                    );
                }
                $analyzer = new Analyzer($logger, new Structure(), true);
                $this->parser = new Parser($analyzer, $metadata['json_parser.struct']);
            }
        } else {
            if ($compatLevel === self::LEGACY_VERSION) {
                $logger->warning('Using legacy JSON parser, because it has been explicitly requested.');
                $structure = new Struct($logger);
                $structure->setAutoUpgradeToArray(true);
                $analyzer = new LegacyAnalyzer($logger, $structure, -1);
                $analyzer->setNestedArrayAsJson(true);
                $this->parser = new LegacyParser($logger, $analyzer, $structure);
            } else {
                $this->parser = new Parser(new Analyzer($logger, new Structure(), true));
            }
        }
        $this->parser->setCacheMemoryLimit($cacheMemoryLimit);
    }

    /**
     * @inheritdoc
     */
    public function process(array $data, string $type, $parentId = null): void
    {
        try {
            $this->parser->process($data, $type, $parentId);
        } catch (NoDataException $e) {
            $this->logger->debug("No data returned in '{$type}'");
        } catch (\KeboolaLegacy\Json\Exception\NoDataException $e) {
            $this->logger->debug("No data returned in '{$type}'");
        } catch (JsonParserException $e) {
            throw new UserException(
                'Error parsing response JSON: ' . $e->getMessage(),
                500,
                $e,
                $e->getData()
            );
        } catch (\KeboolaLegacy\Json\Exception\JsonParserException $e) {
            throw new UserException(
                'Error parsing response JSON: ' . $e->getMessage(),
                500,
                $e,
                $e->getData()
            );
        }
    }

    /**
     * Return the results list
     * @return Table[]
     */
    public function getResults(): array
    {
        return $this->parser->getCsvFiles();
    }

    public function getMetadata(): array
    {
        if ($this->parser instanceof LegacyParser) {
            return [
                'json_parser.struct' => $this->parser->getStruct()->getData(),
                'json_parser.structVersion' => $this->parser->getStruct()::getStructVersion(),
            ];
        } else {
            return [
                'json_parser.struct' => $this->parser->getAnalyzer()->getStructure()->getData(),
                'json_parser.structVersion' => $this->parser->getAnalyzer()->getStructure()->getVersion(),
            ];
        }
    }
}
