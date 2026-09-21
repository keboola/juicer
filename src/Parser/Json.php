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
use Psr\Log\LoggerInterface;

/**
 * Parse JSON results from REST API to CSV
 */
class Json implements ParserInterface
{
    public const LATEST_VERSION = 3;

    protected Parser $parser;

    protected LoggerInterface $logger;

    public function __construct(
        LoggerInterface $logger,
        array $metadata,
        int $cacheMemoryLimit = 2000000,
    ) {
        $this->logger = $logger;
        if (!empty($metadata['json_parser.struct']) && is_array($metadata['json_parser.struct']) &&
            !empty($metadata['json_parser.structVersion'])) {
            $analyzer = new Analyzer($logger, new Structure(), true);
            $this->parser = new Parser($analyzer, $metadata['json_parser.struct']);
        } else {
            $this->parser = new Parser(new Analyzer($logger, new Structure(), true));
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
        } catch (JsonParserException $e) {
            throw new UserException(
                'Error parsing response JSON: ' . $e->getMessage(),
                500,
                $e,
                $e->getData(),
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
        return [
            'json_parser.struct' => $this->parser->getAnalyzer()->getStructure()->getData(),
            'json_parser.structVersion' => $this->parser->getAnalyzer()->getStructure()->getVersion(),
        ];
    }
}
