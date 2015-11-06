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

    public function testCreate()
    {
        $path = ROOT_PATH . '/Tests/config.yml';
        $file = YamlFile::create($path);

        self::assertEquals(
            \Symfony\Component\Yaml\Yaml::parse(file_get_contents($path)),
            $file->getData()
        );
    }

    /**
     * @expectedException Keboola\Juicer\Exception\ApplicationException
     * @expectedExceptionMessage Error creating file '/asd/123'
     */
    public function testCreateWError()
    {
        $yamlFile = YamlFile::create('/asd/123', 'w');
    }
}
