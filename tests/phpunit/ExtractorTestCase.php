<?php

declare(strict_types=1);

namespace Keboola\Juicer\Tests;

use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class ExtractorTestCase extends TestCase
{
    protected static TestHandler $testHandler;

    protected Logger $logger;

    protected function setUp(): void
    {
        self::$testHandler = new TestHandler();
        $this->logger = new Logger('juicer-test-logger');
        $this->logger->setHandlers([self::$testHandler]);
        parent::setUp();
    }

    /**
     * @param mixed $level
     */
    public static function assertLoggerContains(string $message, $level): void
    {
        self::assertTrue(
            self::$testHandler->hasRecordThatContains($message, $level),
            sprintf('Failed asserting that log contains message "%s" with level "%s".', $message, $level),
        );
    }

    /**
     * @return mixed
     */
    protected static function callMethod(object $obj, string $name, array $args)
    {
        $class = new ReflectionClass($obj);
        $method = $class->getMethod($name);
        $method->setAccessible(true);

        return $method->invokeArgs($obj, $args);
    }

    /**
     * @return mixed
     */
    protected static function getProperty(object $obj, string $name)
    {
        $class = new ReflectionClass($obj);
        $property = $class->getProperty($name);
        $property->setAccessible(true);
        return $property->getValue($obj);
    }
}
