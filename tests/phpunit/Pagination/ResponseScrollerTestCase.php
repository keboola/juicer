<?php

declare(strict_types=1);

namespace Keboola\Juicer\Tests\Pagination;

use Keboola\Juicer\Config\JobConfig;
use Keboola\Juicer\Tests\ExtractorTestCase;

class ResponseScrollerTestCase extends ExtractorTestCase
{
    protected function getConfig(): JobConfig
    {
        return new JobConfig([
            'endpoint' => 'test',
            'params' => [
                'a' => 1,
                'b' => 2,
            ],
        ]);
    }
}
