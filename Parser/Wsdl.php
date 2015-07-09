<?php

namespace Keboola\ExtractorBundle\Parser;

use	Keboola\ExtractorBundle\Exception\ApplicationException as Exception;
use	Keboola\Utils\Utils;
use	Keboola\CsvTable\Table;
use	Keboola\ExtractorBundle\Parser\Parser;

/**
 * Parse XML results from SOAP API to CSV
 */
class Wsdl extends Parser
{
	/** @var array */
	protected $types;
	/** @var array */
	protected $struct;
	/** @var Table[] */
	protected $csvFiles = array();
	// Generic data types to avoid parsing as WSDL "arrays"
	protected $stdTypes = array(
		"QName",
		"anySimpleType",
		"anyURI",
		"base64Binary",
		"base64Binary",
		"boolean",
		"date",
		"dateTime",
		"double",
		"float",
		"gDay",
		"gMonth",
		"gMonthDay",
		"gYear",
		"gYearMonth",
		"hexBinary",
		"int",
		"integer",
		"string",
		"time"
	);

	/**
	 * @param array $types is generally a WSDL definition, obtained by \SoapClient::__getTypes() (upon a properly initialized client, obviously)
	 */
	public function __construct(array $types) {
		$this->types = $types;
		$this->struct = $this->createStruct();
	}

	/**
	 * Create structure from a WSDL
	 * @param array $types
	 * @param int $maxDepth defines how many levels of the response should be parsed (unlimited by default)
	 */
	protected function createStruct(array $types = array(), $maxDepth = -1)
	{
		$types = !empty($types) ? $types : $this->types;

		$columns = array();
		foreach ($types as $type) {
			$typeArr = preg_split("/ /", $type, 3);

			// Overflow protection. -1 for infinite
			if ($maxDepth == 0) {
				$columns[$typeArr[1]] = $typeArr[0];
				continue;
			}

			if($typeArr[0] == "struct") {
				// Clear ; on line ends
				$str = str_replace(";", "", $typeArr[2]);
				// Get parts of the struct
				$elements = explode("\n", $str);
				// Remove { } lines
				array_splice($elements, 0, 1);
				array_pop($elements);
				// Remove leading/trailing spaces
				$elements = array_map("trim", $elements);
				if (sizeof($elements) == 0) {
					$columns[$typeArr[1]] = null;
				} else {
					$columns[$typeArr[1]] = $this->createStruct($elements, $maxDepth - 1);
				}
			} else {
				// Save simple data types
				$columns[$typeArr[1]] = $typeArr[0];
			}
		}

		return $columns;
	}

	/**
	 * Parse the data
	 * @param array|object $data shall be the response body
	 * @param string $type is a WSDL data type (has to be obtained from the WSDL definition)
	 * @param string $path a path to the results list(the array containing each record) within the response
	 * @param string $parent: used internally for naming child arrays/columns
	 * @param string $parentId: used internally to link child objects to parent
	 * @return Table[]
	 */
	public function parse($data, $type, $path = null, $parent = null, $parentId = null) {
		if (!empty($path)) {
			$data = Utils::getDataFromPath($path, $data);
		}

		$fileName = $type;

		if (empty($this->csvFiles[$fileName])) {
			$header = array_keys($this->struct[$type]);
			if ($parentId) {
				array_push($header, "WSDL_parentId");
			}
			$this->csvFiles[$fileName] = Table::create($fileName, $header, $this->getTemp());
		}

		$handle = $this->csvFiles[$fileName];
		$struct = $this->struct[$type];

		foreach(Utils::to_assoc($data) as $record) {
			$row = array();

			foreach($struct as $key => $valueType) {
				if (empty($record[$key])) {
					$row[$key] = null;
				} elseif (in_array($valueType, $this->stdTypes)) {
					$row[$key] = (string) $record[$key];
				} elseif (array_key_exists($valueType, $this->struct)) {
					// Walk through the data type and parse children
					foreach($this->struct[$valueType] as $attr => $attrType) {
						$childId = $type . "_" . $attrType . "_" . (!empty($row["id"]) ? $row["id"] : uniqid());
						$row[$key] = $childId;
						$childPath = "{$key}/{$attr}";
						$this->parse($record, $attrType, $childPath, $type, $childId);
					}
				} else {
					$row[$key] = null;
				}
			}
			// FIXME set this in the data before actually caling the fn
			if ($parentId) {
				$row["WSDL_parentId"] = $parentId;
			}

			$handle->writeRow($row);
		}

		return $this->getCsvFiles();
	}

	/**
	 * Return the results list
	 * @return Table[]
	 */
	public function getCsvFiles() {
		return $this->csvFiles;
	}
}
