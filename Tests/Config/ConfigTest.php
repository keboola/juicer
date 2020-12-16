<?php

declare(strict_types=1);

namespace Keboola\Juicer\Tests\Config;

use Keboola\Juicer\Config\Config;
use Keboola\Juicer\Config\JobConfig;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    public function testConstructInvalid1(): void
    {
        new Config([]);
    }

    public function testConstructInvalid2(): void
    {
        new Config(['jobs' => 'invalid']);
    }

    public function testConstructInvalid3(): void
    {
        new Config(['jobs' => ['invalid']]);
    }

    public function testConstructInvalid4(): void
    {
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
