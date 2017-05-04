<?php

namespace Keboola\Juicer\Tests\Pagination;

use Keboola\Juicer\Config\JobConfig;
use Keboola\Juicer\Tests\ExtractorTestCase;

class ResponseScrollerTestCase extends ExtractorTestCase
{
    protected function getConfig()
    {
        return new JobConfig('test', [
            'endpoint' => 'test',
            'params' => [
                'a' => 1,
                'b' => 2
            ]
        ]);
    }
}
