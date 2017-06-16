<?php

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
     * @param null $parentId
     */
    public function process(array $data, $type, $parentId = null);


    /**
     * @return Table[]
     */
    public function getResults();

    /**
     * @return array
     */
    public function getMetadata();
}
