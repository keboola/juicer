<?php

declare(strict_types=1);

namespace Keboola\Juicer\Tests\Config;

use Keboola\Juicer\Config\Config;
use Keboola\Juicer\Config\JobConfig;
use Keboola\Juicer\Exception\UserException;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    public function testConstructInvalid1(): void
    {
        $this->expectException(UserException::class);
        $this->expectExceptionMessage("The 'jobs' section is required in the configuration.");
        new Config([]);
    }

    public function testConstructInvalid2(): void
    {
        $this->expectException(UserException::class);
        $this->expectExceptionMessage("The 'jobs' section is required in the configuration.");
        new Config(['jobs' => 'invalid']);
    }

    public function testConstructInvalid3(): void
    {
        $this->expectException(UserException::class);
        $this->expectExceptionMessage("Job configuration must be an array: 'invalid'");
        new Config(['jobs' => ['invalid']]);
    }

    public function testConstructInvalid4(): void
    {
        $this->expectException(UserException::class);
        $this->expectExceptionMessage("The 'endpoint' property must be set in job.");
        new Config(['jobs' => [['still-invalid']]]);
    }

    public function testConstruct(): void
    {
        $config = new Config(['jobs' => [['endpoint' => 'fooBar', 'id' => 'baz']]]);
        self::assertEquals(['baz' => new JobConfig(['endpoint' => 'fooBar', 'id' => 'baz'])], $config->getJobs());
        self::assertEquals([], $config->getAttributes());
        self::assertEquals(null, $config->getAttribute('foo'));
    }

    public function testConstructAttributes(): void
    {
        $config = new Config(['jobs' => [['endpoint' => 'fooBar', 'id' => 'baz']], 'foo' => 'bar', 'bar' => 'baz']);
        self::assertEquals(['baz' => new JobConfig(['endpoint' => 'fooBar', 'id' => 'baz'])], $config->getJobs());
        self::assertEquals(['foo' => 'bar', 'bar' => 'baz'], $config->getAttributes());
        self::assertEquals('bar', $config->getAttribute('foo'));
    }
}
