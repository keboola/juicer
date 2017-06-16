<?php

namespace Keboola\Juicer\Tests\Pagination;

use Keboola\Juicer\Config\JobConfig;

class ResponseScrollerTestCase extends \PHPUnit_Framework_TestCase
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
