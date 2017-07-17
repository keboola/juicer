<?php

namespace Keboola\Juicer\Tests\Config;

use Keboola\Juicer\Config\Config;
use Keboola\Juicer\Config\JobConfig;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    /**
     * @expectedException \Keboola\Juicer\Exception\UserException
     * @expectedExceptionMessage The 'jobs' section is required in the configuration.
     */
    public function testConstructInvalid1()
    {
        new Config([]);
    }

    /**
     * @expectedException \Keboola\Juicer\Exception\UserException
     * @expectedExceptionMessage The 'jobs' section is required in the configuration.
     */
    public function testConstructInvalid2()
    {
        new Config(['jobs' => 'invalid']);
    }

    /**
     * @expectedException \Keboola\Juicer\Exception\UserException
     * @expectedExceptionMessage Job configuration must be an array: 'invalid'
     */
    public function testConstructInvalid3()
    {
        new Config(['jobs' => ['invalid']]);
    }

    /**
     * @expectedException \Keboola\Juicer\Exception\UserException
     * @expectedExceptionMessage The 'endpoint' property must be set in job.
     */
    public function testConstructInvalid4()
    {
        new Config(['jobs' => [['still-invalid']]]);
    }

    public function testConstruct()
    {
        $config = new Config(['jobs' => [['endpoint' => 'fooBar', 'id' => 'baz']]]);
        self::assertEquals(['baz' => new JobConfig(['endpoint' => 'fooBar', 'id' => 'baz'])], $config->getJobs());
        self::assertEquals([], $config->getAttributes());
        self::assertEquals(null, $config->getAttribute('foo'));
    }

    public function testConstructAttributes()
    {
        $config = new Config(['jobs' => [['endpoint' => 'fooBar', 'id' => 'baz']], 'foo' => 'bar', 'bar' => 'baz']);
        self::assertEquals(['baz' => new JobConfig(['endpoint' => 'fooBar', 'id' => 'baz'])], $config->getJobs());
        self::assertEquals(['foo' => 'bar', 'bar' => 'baz'], $config->getAttributes());
        self::assertEquals('bar', $config->getAttribute('foo'));
    }
}