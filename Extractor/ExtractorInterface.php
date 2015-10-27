<?php
namespace Keboola\Juicer\Extractor;

use Keboola\CsvTable\Table;
use    Keboola\Juicer\Config\Config;

interface ExtractorInterface
{
//     public function __construct();

    /**
     * @param array $config
     *    [
     *        "attributes": [array of attributes of the config],
     *        "data": [raw data of the configuration (DEPRECATED)],
     *        "jobs": \Keboola\Juicer\Common\JobConfig[]
     *    ]
     * @param array $params parameters of the call
     *     - should contain "config" string including the name of the config called (DEPRECATED?)
     * @return Table[]
     */
    public function run(Config $config);
}
