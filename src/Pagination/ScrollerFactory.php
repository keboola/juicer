<?php

declare(strict_types=1);

namespace Keboola\Juicer\Pagination;

use Keboola\Juicer\Exception\UserException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class ScrollerFactory
{
    public static function getScroller(array $config, ?LoggerInterface $logger = null): ScrollerInterface
    {
        $scroller = self::createScroller($config, $logger ?? new NullLogger);
        $scroller = self::decorateScroller($scroller, $config, $logger ?? new NullLogger);
        return $scroller;
    }

    private static function decorateScroller(
        ScrollerInterface $scroller,
        array $config,
        LoggerInterface $logger
    ): ScrollerInterface {
        if (!empty($config['nextPageFlag'])) {
            $scroller = new Decorator\HasMoreScrollerDecorator($scroller, $config, $logger);
        }

        if (!empty($config['forceStop'])) {
            $scroller = new Decorator\ForceStopScrollerDecorator($scroller, $config, $logger);
        }

        if (!empty($config['limitStop'])) {
            $scroller = new Decorator\LimitStopScrollerDecorator($scroller, $config, $logger);
        }

        return $scroller;
    }

    private static function createScroller(array $config, LoggerInterface $logger): ScrollerInterface
    {
        if (empty($config['method'])) {
            return new NoScroller();
        }

        switch ($config['method']) {
            case 'offset':
                return new OffsetScroller($config, $logger);
            case 'response.param':
                return new ResponseParamScroller($config, $logger);
            case 'response.url':
                return new ResponseUrlScroller($config, $logger);
            case 'zendesk.response.url':
                return new ZendeskResponseUrlScroller($config, $logger);
            case 'pagenum':
                return new PageScroller($config, $logger);
            case 'cursor':
                return new CursorScroller($config, $logger);
            case 'multiple':
                return new MultipleScroller($config, $logger);
            default:
                $method = is_string($config['method'])
                    ? $config['method']
                    : json_encode($config['method']);
                throw new UserException("Unknown pagination method '{$method}'");
        }
    }
}
