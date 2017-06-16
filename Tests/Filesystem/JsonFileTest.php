<?php

namespace Keboola\Juicer\Tests\FileSystem;

use Keboola\Juicer\Filesystem\JsonFile;
use Keboola\Juicer\Tests\ExtractorTestCase;
use Keboola\Temp\Temp;
use Symfony\Component\Filesystem\Filesystem;

class JsonFileTest extends ExtractorTestCase
{

    public function testGet()
    {
        $file = new JsonFile('');
        $file->setData([
            'first' => [
                'second' => 'value'
            ]
        ]);

        self::assertEquals('value', $file->get('first', 'second'));
    }

    public function testCreate()
    {
        $temp = new Temp();
        $fs = new Filesystem();
        $path = $temp->getTmpFolder() . DIRECTORY_SEPARATOR . 'test.json';
        $fs->dumpFile($path, '{"some": {"valid": "JSON"}}');
        $file = JsonFile::create($path);

        self::assertNotEmpty($file->getData());
        self::assertEquals(
            json_decode(file_get_contents($path), true),
            $file->getData()
        );
    }

    /**
     * @expectedException \Keboola\Juicer\Exception\ApplicationException
     * @expectedExceptionMessage Invalid JSON Syntax error
     */
    public function testCreateInvalid()
    {
        $temp = new Temp();
        $fs = new Filesystem();
        $path = $temp->getTmpFolder() . DIRECTORY_SEPARATOR . 'test.json';
        $fs->dumpFile($path, 'some-invalid-json');
        JsonFile::create($path);
    }

    /**
     * @expectedException \Keboola\Juicer\Exception\ApplicationException
     * @expectedExceptionMessage Error creating file '/asd/123'
     */
    public function testCreateWError()
    {
        JsonFile::create('/asd/123', 'w');
    }
}
