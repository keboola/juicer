<?php

namespace Keboola\Juicer\Tests\Config;

use Keboola\Juicer\Config\Configuration;
use Keboola\Juicer\Config\JobConfig;
use Keboola\Juicer\Tests\ExtractorTestCase;
use Keboola\Temp\Temp;
use Keboola\CsvTable\Table;

class ConfigurationTest extends ExtractorTestCase
{
    public function testStoreResults()
    {
        $resultsPath = './data/storeResultsTest' . uniqid();

        $this->storeResults($resultsPath, 'full', false);
    }

    public function testIncrementalResults()
    {
        $resultsPath = './data/storeResultsTest' . uniqid();

        $this->storeResults($resultsPath, 'incremental', true);
    }

    public function testDefaultBucketResults()
    {
        $resultsPath = './data/storeResultsDefaultBucket' . uniqid();

        $configuration = new Configuration($resultsPath, 'defaultBucket', new Temp('test'));

        $files = [
            Table::create('first', ['col1', 'col2']),
            Table::create('second', ['col11', 'col12'])
        ];

        $files[0]->writeRow(['a', 'b']);
        $files[1]->writeRow(['c', 'd']);
        $files[1]->setPrimaryKey(['col11']);

        $configuration->storeResults($files);

        foreach (new \DirectoryIterator('./Tests/data/storeResultsDefaultBucket/out/tables/') as $file) {
            self::assertFileEquals($file->getPathname(), $resultsPath . '/out/tables/' . $file->getFilename());
        }

        $this->rmDir($resultsPath);
    }

    protected function storeResults($resultsPath, $name, $incremental)
    {
        $configuration = new Configuration($resultsPath, $name, new Temp('test'));

        $files = [
            Table::create('first', ['col1', 'col2']),
            Table::create('second', ['col11', 'col12'])
        ];

        $files[0]->writeRow(['a', 'b']);
        $files[1]->writeRow(['c', 'd']);

        $configuration->storeResults($files, $name, true, $incremental);

        foreach (new \DirectoryIterator('./Tests/data/storeResultsTest/out/tables/' . $name) as $file) {
            self::assertFileEquals($file->getPathname(), $resultsPath . '/out/tables/' . $name . '/' . $file->getFilename());
        }

        $this->rmDir($resultsPath);
    }

    public function testGetConfigMetadata()
    {
        $path = __DIR__ . '/../data/metadataTest';

        $configuration = new Configuration($path, 'test', new Temp('test'));
        $json = $configuration->getConfigMetadata();

        self::assertEquals(json_decode('{"some":"data","more": {"woah": "such recursive"}}', true), $json);

        $noConfiguration = new Configuration('asdf', 'test', new Temp('test'));
        self::assertEquals(null, $noConfiguration->getConfigMetadata());
    }

    public function testSaveConfigMetadata()
    {
        $resultsPath = './data/metadataTest' . uniqid();

        $configuration = new Configuration($resultsPath, 'test', new Temp('test'));

        $configuration->saveConfigMetadata([
            'some' => 'data',
            'more' => [
                'woah' => 'such recursive'
            ]
        ]);

        self::assertFileEquals('./Tests/data/metadataTest/out/state.json', $resultsPath . '/out/state.json');

        $this->rmDir($resultsPath);
    }

    public function testGetConfig()
    {
        $configuration = new Configuration('./Tests/data/recursive', 'test', new Temp('test'));

        $config = $configuration->getConfig();

        $json = json_decode(file_get_contents('./Tests/data/recursive/config.json'), true);

        $jobs = $config->getJobs();
        self::assertEquals(JobConfig::create($json['parameters']['config']['jobs'][0]), reset($jobs));

        self::assertEquals($json['parameters']['config']['outputBucket'], $config->getAttribute('outputBucket'));
    }

    public function testGetMultipleConfigs()
    {
        $configuration = new Configuration('./Tests/data/iterations', 'test', new Temp('test'));

        $configs = $configuration->getMultipleConfigs();

        $json = json_decode(file_get_contents('./Tests/data/iterations/config.json'), true);

        foreach ($json['parameters']['iterations'] as $i => $params) {
            self::assertEquals(array_replace(['id' => $json['parameters']['config']['id']], $params), $configs[$i]->getAttributes());
        }
        self::assertEquals($configs[0]->getJobs(), $configs[1]->getJobs());
        self::assertContainsOnlyInstancesOf('\Keboola\Juicer\Config\Config', $configs);
        self::assertCount(count($json['parameters']['iterations']), $configs);
    }

    public function testGetMultipleConfigsSingle()
    {
        $configuration = new Configuration('./Tests/data/simple_basic', 'test', new Temp('test'));

        $configs = $configuration->getMultipleConfigs();

        $json = json_decode(file_get_contents('./Tests/data/simple_basic/config.json'), true);

        self::assertContainsOnlyInstancesOf('\Keboola\Juicer\Config\Config', $configs);
        self::assertCount(1, $configs);

        self::assertEquals($configuration->getConfig(), $configs[0]);
    }

    public function testGetJson()
    {
        $configuration = new Configuration('./Tests/data/simple_basic', 'test', new Temp('test'));

        $result = self::callMethod($configuration, 'getJson', ['/config.json', 'parameters', 'config', 'id']);

        self::assertEquals('multiCfg', $result);
    }

    protected function rmDir($dirPath)
    {
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dirPath, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $path) {
            $path->isDir() && !$path->isLink() ? rmdir($path->getPathname()) : unlink($path->getPathname());
        }
        return rmdir($dirPath);
    }
}
