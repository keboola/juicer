<?php

namespace Keboola\Juicer\Tests\Config;

use Keboola\Juicer\Config\JobConfig;
use PHPUnit\Framework\TestCase;

class JobConfigTest extends TestCase
{
    /**
     * @expectedException \Keboola\Juicer\Exception\UserException
     * @expectedExceptionMessage The 'endpoint' property must be set in job.
     */
    public function testConstructInvalid()
    {
        new JobConfig([]);
    }

    public function testConstructDefault()
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

    public function testConstruct()
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

    /**
     * @expectedException \Keboola\Juicer\Exception\UserException
     * @expectedExceptionMessage The 'children' property must an array of jobs.
     */
    public function testConstructChildrenInvalid1()
    {
        new JobConfig(['endpoint' => 'fooBar', 'children' => 'invalid']);
    }

    /**
     * @expectedException \Keboola\Juicer\Exception\UserException
     * @expectedExceptionMessage Job configuration must be an array: 'invalid'
     */
    public function testConstructChildrenInvalid2()
    {
        new JobConfig(['endpoint' => 'fooBar', 'children' => ['invalid']]);
    }

    /**
     * @expectedException \Keboola\Juicer\Exception\UserException
     * @expectedExceptionMessage The 'endpoint' property must be set in job.
     */
    public function testConstructChildrenInvalid3()
    {
        new JobConfig(['endpoint' => 'fooBar', 'children' => [['invalid']]]);
    }

    /**
     * @expectedException \Keboola\Juicer\Exception\UserException
     * @expectedExceptionMessage The 'params' property must be an array.
     */
    public function testConstructChildrenInvalid4()
    {
        new JobConfig(['endpoint' => 'fooBar', 'params' => 'invalid']);
    }

    public function testConstructChildren()
    {
        $job = new JobConfig(['endpoint' => 'fooBar', 'children' => [['endpoint' => 'fooBar', 'id' => 'baz']]]);
        self::assertEquals(['baz' => new JobConfig(['endpoint' => 'fooBar', 'id' => 'baz'])], $job->getChildJobs());
    }

    public function testConstructSet()
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
