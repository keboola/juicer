<?php

use Keboola\Juicer\Exception\ApplicationException;

class ApplicationExceptionTest extends ExtractorTestCase
{
    public function testSetData()
    {
        $data = ['data' => 'test'];
        $e = new ApplicationException("test", 0, null, $data);
        self::assertEquals($data, $e->getData());
    }
}
