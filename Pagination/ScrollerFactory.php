<?php

namespace Keboola\Juicer\Pagination;

use Keboola\Juicer\Exception\UserException;

class ScrollerFactory
{
    /**
     * @param array $config
     * @return ScrollerInterface
     * @throws UserException
     */
    public static function getScroller(array $config)
    {
        $scroller = self::createScroller($config);
        $scroller = self::decorateScroller($scroller, $config);
        return $scroller;
    }

    private static function decorateScroller(ScrollerInterface $scroller, array $config)
    {
        if (!empty($config['nextPageFlag'])) {
            $scroller = new Decorator\HasMoreScrollerDecorator($scroller, $config);
        }

        if (!empty($config['forceStop'])) {
            $scroller = new Decorator\ForceStopScrollerDecorator($scroller, $config);
        }

        if (!empty($config['limitStop'])) {
            $scroller = new Decorator\LimitStopScrollerDecorator($scroller, $config);
        }

        return $scroller;
    }

    private static function createScroller(array $config)
    {
        if (empty($config['method'])) {
            return new NoScroller();
        }

        switch ($config['method']) {
            case 'offset':
                return new OffsetScroller($config);
            case 'response.param':
                return new ResponseParamScroller($config);
            case 'response.url':
                return new ResponseUrlScroller($config);
            case 'zendesk.response.url':
                return new ZendeskResponseUrlScroller($config);
            case 'pagenum':
                return new PageScroller($config);
            case 'cursor':
                return new CursorScroller($config);
            case 'multiple':
                return new MultipleScroller($config);
            default:
                $method = is_string($config['method'])
                    ? $config['method']
                    : json_encode($config['method']);
                throw new UserException("Unknown pagination method '{$method}'");
        }
    }
}
