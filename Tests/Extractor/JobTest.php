<?php

use Keboola\Juicer\Config\JobConfig,
    Keboola\Juicer\Client\RestClient,
    Keboola\Juicer\Parser\Json,
    Keboola\Juicer\Extractor\Job;

use Keboola\Json\Parser;

class JobTest extends ExtractorTestCase
{
    public function testGetDataType()
    {
        $jobConfig = JobConfig::create(['endpoint' => 'resources/res.json', 'dataType' => 'res']);

        $job = $this->getMockForAbstractClass(
            'Keboola\Juicer\Extractor\Job',
            [
                $jobConfig,
                RestClient::create(),
                new Json(Parser::create($this->getLogger('job', true)))
            ]
        );

        $this->assertEquals($jobConfig->getDataType(), $this->callMethod($job, 'getDataType', []));
    }

    public function testGetDataTypeFromEndpoint()
    {
        $jobConfig = JobConfig::create(['endpoint' => 'resources/res.json']);

        $job = $this->getMockForAbstractClass(
            'Keboola\Juicer\Extractor\Job',
            [
                $jobConfig,
                RestClient::create(),
                new Json(Parser::create($this->getLogger('job', true)))
            ]
        );

        $this->assertEquals($jobConfig->getEndpoint(), $this->callMethod($job, 'getDataType', []));
    }
}
