<?php

namespace Keboola\Juicer\Tests\Exception;

use Keboola\Juicer\Exception\ApplicationException;
use PHPUnit\Framework\TestCase;

class ApplicationExceptionTest extends TestCase
{
    public function testSetData()
    {
        $data = ['data' => 'test'];
        $e = new ApplicationException("test", 0, null, $data);
        self::assertEquals($data, $e->getData());
    }
}
