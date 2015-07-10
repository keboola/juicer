<?php

use Keboola\Juicer\Dummy\DummyExtractor;

use Keboola\Juicer\Syrup\Job\Executor,
	Keboola\Juicer\Config\Configuration;
use	Keboola\Temp\Temp;

require_once(dirname(__FILE__) . "/vendor/autoload.php");

const APP_NAME = 'ex-test';

$temp = new Temp(APP_NAME);


// $arguments = getopt("d::", array("data::"));
// if (!isset($arguments["data"])) {
// 	print "Data folder not set.";
// 	exit(1);
// }
$arguments = ['data' => './Tests'];

$configuration = new Configuration(APP_NAME, $temp);
$config = $configuration->getConfig($arguments['data']);
// $executor = new Executor($configuration);

$extractor = new DummyExtractor($temp);
$extractor->run($config);
