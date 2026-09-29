<?php

declare(strict_types=1);

namespace Keboola\Juicer\Parser;

use Keboola\CsvTable\Table;

/**
 * A parser should be able to process an array of data and return results in a list of CSV files
 */
interface ParserInterface
{
    /**
     * Parse the data
     * @param array $data shall be the response body
     * @param string $type data type
     */
    public function process(array $data, string $type, string|array|null $parentId = null): void;


    /**
     * @return Table[]
     */
    public function getResults(): array;

    public function getMetadata(): array;
}
