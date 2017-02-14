<?php

use Keboola\Juicer\Filesystem\JsonFile;

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
        $path = ROOT_PATH . '/Tests/config.yml';
        $file = JsonFile::create($path);

        self::assertEquals(
            json_decode(file_get_contents($path), true),
            $file->getData()
        );
    }

    /**
     * @expectedException Keboola\Juicer\Exception\ApplicationException
     * @expectedExceptionMessage Error creating file '/asd/123'
     */
    public function testCreateWError()
    {
        JsonFile::create('/asd/123', 'w');
    }
}
