<?php

declare(strict_types=1);

namespace Keboola\Juicer\Tests\Tests;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Keboola\Juicer\Tests\HistoryContainer;
use OutOfRangeException;
use PHPUnit\Framework\TestCase;
use UnderflowException;

class HistoryContainerTest extends TestCase
{
    public function testCount(): void
    {
        $container = new HistoryContainer();
        self::assertSame(0, $container->count());

        $container[] = $this->createHistoryItemArray();
        self::assertSame(1, $container->count());

        $container[] = $this->createHistoryItemArray();
        self::assertSame(2, $container->count());
    }

    public function testIsEmpty(): void
    {
        $container = new HistoryContainer();
        self::assertTrue($container->isEmpty());

        $container[] = $this->createHistoryItemArray();
        self::assertFalse($container->isEmpty());

        $container[] = $this->createHistoryItemArray();
        self::assertFalse($container->isEmpty());
    }

    public function testPopEmpty(): void
    {
        $container = new HistoryContainer();
        $this->expectException(UnderflowException::class);
        $this->expectExceptionMessage('No more history items.');
        $container->pop();
    }

    public function testPop(): void
    {
        $container = new HistoryContainer();
        $container[] = $this->createHistoryItemArray('item1');
        $container[] = $this->createHistoryItemArray('item2');
        self::assertSame(2, $container->count());

        self::assertSame('item2', (string) $container->pop()->getResponse()->getBody());
        self::assertSame(1, $container->count());

        self::assertSame('item1', (string) $container->pop()->getResponse()->getBody());
        self::assertSame(0, $container->count());

        $this->expectException(UnderflowException::class);
        $this->expectExceptionMessage('No more history items.');
        $container->pop();
    }

    public function testShiftEmpty(): void
    {
        $container = new HistoryContainer();
        $this->expectException(UnderflowException::class);
        $this->expectExceptionMessage('No more history items.');
        $container->shift();
    }

    public function testShift(): void
    {
        $container = new HistoryContainer();
        $container[] = $this->createHistoryItemArray('item1');
        $container[] = $this->createHistoryItemArray('item2');
        self::assertSame(2, $container->count());

        self::assertSame('item1', (string) $container->shift()->getResponse()->getBody());
        self::assertSame(1, $container->count());

        self::assertSame('item2', (string) $container->shift()->getResponse()->getBody());
        self::assertSame(0, $container->count());

        $this->expectException(UnderflowException::class);
        $this->expectExceptionMessage('No more history items.');
        $container->shift();
    }

    public function testFirstEmpty(): void
    {
        $container = new HistoryContainer();
        $this->expectException(OutOfRangeException::class);
        $this->expectExceptionMessage('No history items.');
        $container->first();
    }

    public function testFirst(): void
    {
        $container = new HistoryContainer();
        $container[] = $this->createHistoryItemArray('item1');
        $container[] = $this->createHistoryItemArray('item2');
        self::assertSame(2, $container->count());

        self::assertSame('item1', (string) $container->first()->getResponse()->getBody());
        self::assertSame(2, $container->count());

        self::assertSame('item1', (string) $container->first()->getResponse()->getBody());
        self::assertSame(2, $container->count());
    }

    public function testLastEmpty(): void
    {
        $container = new HistoryContainer();
        $this->expectException(OutOfRangeException::class);
        $this->expectExceptionMessage('No history items.');
        $container->last();
    }

    public function testLast(): void
    {
        $container = new HistoryContainer();
        $container[] = $this->createHistoryItemArray('item1');
        $container[] = $this->createHistoryItemArray('item2');
        self::assertSame(2, $container->count());

        self::assertSame('item2', (string) $container->last()->getResponse()->getBody());
        self::assertSame(2, $container->count());

        self::assertSame('item2', (string) $container->last()->getResponse()->getBody());
        self::assertSame(2, $container->count());
    }

    private function createHistoryItemArray(?string $body = 'body'): array
    {
        return [
           'request' => new Request('GET', 'example.com'),
           'response' => new Response(200, [], $body),
           'error' => null,
           'options' => ['foo' => 'bar'],
        ];
    }
}
