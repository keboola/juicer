<?php

namespace Keboola\Juicer\Tests\Extractor;

use Keboola\Juicer\Config\JobConfig;
use Keboola\Juicer\Client\RestClient;
use Keboola\Juicer\Extractor\Job;
use Keboola\Juicer\Parser\Json;
use Keboola\Json\Parser;
use Keboola\Juicer\Tests\ExtractorTestCase;
use Psr\Log\NullLogger;

class JobTest extends ExtractorTestCase
{
    public function testGetDataType()
    {
        $jobConfig = JobConfig::create(['endpoint' => 'resources/res.json', 'dataType' => 'res']);

        $job = $this->getMockForAbstractClass(
            Job::class,
            [
                $jobConfig,
                RestClient::create(new NullLogger()),
                new Json(Parser::create(new NullLogger()), new NullLogger()),
                new NullLogger()
            ]
        );

        self::assertEquals($jobConfig->getDataType(), self::callMethod($job, 'getDataType', []));
    }

    public function testGetDataTypeFromEndpoint()
    {
        $jobConfig = JobConfig::create(['endpoint' => 'resources/res.json']);

        $job = $this->getMockForAbstractClass(
            Job::class,
            [
                $jobConfig,
                RestClient::create(new NullLogger()),
                new Json(Parser::create(new NullLogger()), new NullLogger()),
                new NullLogger()
            ]
        );

        self::assertEquals($jobConfig->getEndpoint(), self::callMethod($job, 'getDataType', []));
    }
}
