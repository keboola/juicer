<?php

namespace Keboola\Juicer\Parser;

use Keboola\CsvTable\Table;
use Keboola\Json\Analyzer;
use Keboola\Json\Parser as JsonParser;
use Keboola\Json\Exception\JsonParserException;
use Keboola\Json\Exception\NoDataException;
use Keboola\Json\Structure;
use Keboola\Juicer\Exception\UserException;
use Keboola\Juicer\Exception\ApplicationException;
use Keboola\Temp\Temp;
use Psr\Log\LoggerInterface;

/**
 * Parse JSON results from REST API to CSV
 */
class Json implements ParserInterface
{
    /**
     * @var JsonParser
     */
    protected $parser;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Json constructor.
     * @param LoggerInterface $logger
     * @param Temp $temp
     * @param array $metadata
     */
    public function __construct(LoggerInterface $logger, Temp $temp, array $metadata = [])
    {
        $structure = new Structure();
        if (!empty($metadata['json_parser.struct']) && is_array($metadata['json_parser.struct'])) {
            if (empty($metadata['json_parser.structVersion'])
                || $metadata['json_parser.structVersion'] != $structure->getVersion()
            ) {
                // temporary
                $metadata['json_parser.struct'] = self::updateStruct($metadata['json_parser.struct']);
            }

            $struct = $metadata['json_parser.struct'];
        } else {
            $struct = [];
        }

        $structure->load($struct);
        $this->parser = new JsonParser(new Analyzer($logger, $structure));
        $this->logger = $logger;
    }

    /**
     * @inheritdoc
     */
    public function process(array $data, $type, $parentId = null)
    {
        try {
            $this->parser->process($data, $type, $parentId);
        } catch (NoDataException $e) {
            $this->logger->debug("No data returned in '{$type}'");
        } catch (JsonParserException $e) {
            throw new UserException(
                "Error parsing response JSON: " . $e->getMessage(),
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
    public function getResults()
    {
        return $this->parser->getCsvFiles();
    }

    /**
     * @return JsonParser
     */
    public function getParser()
    {
        return $this->parser;
    }

    /**
     * @return array
     */
    public function getMetadata()
    {
        return [
            'json_parser.struct' => $this->parser->getAnalyzer()->getStructure()->getData(),
            'json_parser.structVersion' => $this->parser->getAnalyzer()->getStructure()->getVersion()
        ];
    }

    protected static function updateStruct(array $struct)
    {
        foreach ($struct as $type => &$children) {
            if (!is_array($children)) {
                throw new ApplicationException("Error updating struct at '{$type}', an array was expected");
            }

            foreach ($children as $child => &$dataType) {
                if (in_array($dataType, ['integer', 'double', 'string', 'boolean'])) {
                    // Make scalars non-strict
                    $dataType = 'scalar';
                } elseif ($dataType == 'array') {
                    // Determine array types
                    if (!empty($struct["{$type}.{$child}"])) {
                        $childType = $struct["{$type}.{$child}"];
                        if (array_keys($childType) == ['data']) {
                            $dataType = 'arrayOfscalar';
                        } else {
                            $dataType = 'arrayOfobject';
                        }
                    }
                }
            }
        }

        return $struct;
    }
}
