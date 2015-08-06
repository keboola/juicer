<?php

namespace Keboola\Juicer\Parser;

use	Keboola\CsvTable\Table;

/**
 * A parser should be able to process an array of data and return results in a list of CSV files
 */
interface ParserInterface
{
	/**
	 * @param array $data
	 * @param string $type
	 * @todo add $parentId for recursion links?
	 */
	public function process(array $data, $type);


	/**
	 * @return Table[]
	 */
	public function getResults();
}
