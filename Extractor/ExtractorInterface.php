<?php

namespace Keboola\Juicer\Extractor;

use Keboola\CsvTable\Table;
use Keboola\Juicer\Config\Config;

interface ExtractorInterface
{
    /**
     * @param array|Config $config
     *    [
     *        "attributes": [array of attributes of the config],
     *        "data": [raw data of the configuration (DEPRECATED)],
     *        "jobs": \Keboola\Juicer\Common\JobConfig[]
     *    ]
     * @return Table[]
     * @internal param array $params parameters of the call
     *     - should contain "config" string including the name of the config called (DEPRECATED?)
     */
    public function run(Config $config);
}
