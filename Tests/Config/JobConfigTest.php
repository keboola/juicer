<?php

declare(strict_types=1);

namespace Keboola\Juicer\Tests\Config;

use Keboola\Juicer\Config\JobConfig;
use Keboola\Juicer\Exception\UserException;
use PHPUnit\Framework\TestCase;

class JobConfigTest extends TestCase
{
    public function testConstructInvalid(): void
    {
        $this->expectException(UserException::class);
        $this->expectExceptionMessage("The 'endpoint' property must be set in job.");
        new JobConfig([]);
    }

    public function testConstructDefault(): void
    {
        $job = new JobConfig(['endpoint' => 'fooBar']);
        self::assertEquals('fooBar', $job->getEndpoint());
        self::assertEquals([], $job->getParams());
        self::assertEquals([], $job->getChildJobs());
        $cfg = $job->getConfig();
        self::assertArrayHasKey('id', $cfg);
        unset($cfg['id']);
        self::assertEquals(['endpoint' => 'fooBar', 'params' => [], 'dataType' => 'fooBar'], $cfg);
        self::assertEquals('fooBar', $job->getDataType());
        self::assertNotEmpty($job->getJobId());
    }

    public function testConstruct(): void
    {
        $config = ['endpoint' => 'fooBar', 'id' => 'baz', 'params' => ['a' => 'b'], 'dataType' => 'dt'];
        $job = new JobConfig($config);
        self::assertEquals('fooBar', $job->getEndpoint());
        self::assertEquals(['a' => 'b'], $job->getParams());
        self::assertEquals([], $job->getChildJobs());
        self::assertEquals($config, $job->getConfig());
        self::assertEquals('dt', $job->getDataType());
        self::assertEquals('baz', $job->getJobId());
    }

    public function testConstructChildrenInvalid1(): void
    {
        $this->expectException(UserException::class);
        $this->expectExceptionMessage("The 'children' property must an array of jobs.");
        new JobConfig(['endpoint' => 'fooBar', 'children' => 'invalid']);
    }

    public function testConstructChildrenInvalid2(): void
    {
        $this->expectException(UserException::class);
        $this->expectExceptionMessage("Job configuration must be an array: 'invalid'");
        new JobConfig(['endpoint' => 'fooBar', 'children' => ['invalid']]);
    }

    public function testConstructChildrenInvalid3(): void
    {
        $this->expectException(UserException::class);
        $this->expectExceptionMessage("The 'endpoint' property must be set in job.");
        new JobConfig(['endpoint' => 'fooBar', 'children' => [['invalid']]]);
    }

    public function testConstructChildrenInvalid4(): void
    {
        $this->expectException(UserException::class);
        $this->expectExceptionMessage("The 'params' property must be an array.");
        new JobConfig(['endpoint' => 'fooBar', 'params' => 'invalid']);
    }

    public function testConstructChildren(): void
    {
        $job = new JobConfig(['endpoint' => 'fooBar', 'children' => [['endpoint' => 'fooBar', 'id' => 'baz']]]);
        self::assertEquals(['baz' => new JobConfig(['endpoint' => 'fooBar', 'id' => 'baz'])], $job->getChildJobs());
    }

    public function testConstructSet(): void
    {
        $config = ['endpoint' => 'fooBar', 'id' => 'baz', 'params' => ['a' => 'b'], 'dataType' => 'dt'];
        $job = new JobConfig($config);
        $job->setEndpoint('barBaz');
        $job->setParams(['b' => 'a']);
        self::assertEquals('barBaz', $job->getEndpoint());
        self::assertEquals(['b' => 'a'], $job->getParams());
        self::assertEquals([], $job->getChildJobs());
        self::assertEquals('dt', $job->getDataType());
        self::assertEquals('baz', $job->getJobId());
        $job->setParam('a', 'bar');
        $job->setParam('b', 'baz');
        self::assertEquals(['b' => 'baz', 'a' => 'bar'], $job->getParams());
    }
}
