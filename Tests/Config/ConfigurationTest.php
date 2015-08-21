<?php

use	Keboola\Juicer\Config\Configuration;
use	Keboola\Temp\Temp;
use	Keboola\CsvTable\Table;

class ConfigurationTest extends ExtractorTestCase
{
	public function testStoreResults()
	{
		$resultsPath = './data/storeResultsTest' . uniqid();

		$configuration = new Configuration($resultsPath, 'test', new Temp('test'));

		$files = [
			Table::create('first', ['col1', 'col2']),
			Table::create('second', ['col11', 'col12'])
		];

		$files[0]->writeRow(['a', 'b']);
		$files[1]->writeRow(['c', 'd']);

		$configuration->storeResults($files, 'test');

		foreach(new \DirectoryIterator('./Tests/data/storeResultsTest/out/tables') as $file) {
			$this->assertFileEquals($file->getPathname(), $resultsPath . '/out/tables/' . $file->getFilename());
		}

		$this->rmDir($resultsPath);
	}

	public function testSaveConfigMetadata()
	{
		$resultsPath = './data/saveMetadataTest' . uniqid();

		$configuration = new Configuration($resultsPath, 'test', new Temp('test'));

		$configuration->saveConfigMetadata([
			'some' => 'data',
			'more' => [
				'woah' => 'such recursive'
			]
		]);

		$this->assertFileEquals('./Tests/data/saveMetadataTest/out/state.yml', $resultsPath . '/out/state.yml');

		$this->rmDir($resultsPath);
	}

	protected function rmDir($dirPath)
	{
		foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dirPath, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $path) {
			$path->isDir() && !$path->isLink() ? rmdir($path->getPathname()) : unlink($path->getPathname());
		}
		return rmdir($dirPath);
	}
}
