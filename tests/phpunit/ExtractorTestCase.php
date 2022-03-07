<?php

declare(strict_types=1);

namespace Keboola\Juicer\Tests;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

class ExtractorTestCase extends TestCase
{
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
