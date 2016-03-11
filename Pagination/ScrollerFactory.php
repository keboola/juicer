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
     */
    public static function getScroller(array $config)
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
