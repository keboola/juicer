<?php

use Keboola\Juicer\Filesystem\YamlFile;

class YamlFileTest extends ExtractorTestCase
{

    public function testGet()
    {
        $file = new YamlFile('');
        $file->setData([
            'first' => [
                'second' => 'value'
            ]
        ]);

        self::assertEquals('value', $file->get('first', 'second'));
    }
}
