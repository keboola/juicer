<?php

namespace Keboola\Juicer\Tests\Extractor;

use    Keboola\Juicer\Extractor\Extractor,
    Keboola\Juicer\Config\Config;

/**
 * Because abstract is right
 */
class MockExtractor extends Extractor
{
    public function run(Config $config)
    {
        // SUCH STUFF WOW
    }
}
