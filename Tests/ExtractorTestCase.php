<?php

class ExtractorTestCase extends \PHPUnit_Framework_TestCase
{
// 	/**
// 	 * @var Keboola\StorageApi\Client
// 	 */
// 	protected $sapiClient;
// 	protected $extractor;
//
// 	public function setUp()
// 	{
// 		$this->extractor = new \Keboola\Juicer\Tests\Extractor\MockExtractor();
// 	}

	protected static function callMethod($obj, $name, array $args)
	{
		$class = new \ReflectionClass($obj);
		$method = $class->getMethod($name);
		$method->setAccessible(true);

		return $method->invokeArgs($obj, $args);
	}

// 	protected static function getProperty($obj, $name) {
// 		$class = new \ReflectionClass($obj);
// 		$property = $class->getProperty($name);
// 		$property->setAccessible(true);
// 		return $property->getValue($obj);
// 	}

	protected function getLogger($name = 'test', $null = false)
	{
		return new \Monolog\Logger(
			$name,
			$null ? [new \Monolog\Handler\NullHandler()] : []
		);
	}
}
