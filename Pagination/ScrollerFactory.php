<?php

namespace Keboola\Juicer\Pagination;

use Keboola\Juicer\Exception\UserException;

/**
 *
 */
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

    protected static function decorateScroller($scroller, $config)
    {
        if (!empty($config['nextPageFlag'])) {
            $scroller = new Decorator\HasMoreScrollerDecorator($scroller, $config);
        }

        if (!empty($config['forceStop'])) {
            $scroller = new Decorator\ForceStopScrollerDecorator($scroller, $config);
        }

        return $scroller;
    }

    protected static function createScroller(array $config)
    {
        if (empty($config['method'])) {
            return NoScroller::create([]);
        }

        switch ($config['method']) {
            case 'offset':
                return OffsetScroller::create($config);
            case 'response.param':
                return ResponseParamScroller::create($config);
            case 'response.url':
                return ResponseUrlScroller::create($config);
            case 'zendesk.response.url':
                return ZendeskResponseUrlScroller::create($config);
            case 'pagenum':
                return PageScroller::create($config);
            case 'cursor':
                return CursorScroller::create($config);
            case 'multiple':
                return MultipleScroller::create($config);
            default:
                $method = is_string($config['method'])
                    ? $config['method']
                    : json_encode($config['method']);
                throw new UserException("Unknown pagination method '{$method}'");
                break;
        }
    }
}
