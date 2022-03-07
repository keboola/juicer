<?php

declare(strict_types=1);

namespace Keboola\Juicer\Client;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Utils;
use Keboola\Juicer\Exception\UserException;
use Psr\Http\Message\RequestInterface;
use function http_build_query;
use function Keboola\Utils\buildUrl;

/**
 * Creates GuzzleRequest from RestRequest
 */
class GuzzleRequestFactory
{
    private ?string $defaultHostHeader;

    public function __construct(?string $defaultHostHeader)
    {
        $this->defaultHostHeader = $defaultHostHeader;
    }

    public function create(RestRequest $restRequest): RequestInterface
    {
        $body = null;
        $headers = $restRequest->getHeaders();
        switch ($restRequest->getMethod()) {
            case 'GET':
                $method = $restRequest->getMethod();
                $endpoint = buildUrl($restRequest->getEndpoint(), $restRequest->getParams());
                break;
            case 'POST':
                $method = $restRequest->getMethod();
                $endpoint = $restRequest->getEndpoint();
                $body = Utils::jsonEncode($restRequest->getParams());
                $headers['Content-Type'] = 'application/json';
                break;
            case 'FORM':
                $method = 'POST';
                $endpoint = $restRequest->getEndpoint();
                $body = http_build_query($restRequest->getParams(), '', '&');
                $headers['Content-Type'] = 'application/x-www-form-urlencoded';
                break;
            default:
                throw new UserException(sprintf(
                    "Unknown request method '%s' for '%s'",
                    $restRequest->getMethod(),
                    $restRequest->getEndpoint()
                ));
        }

        // Set default host header
        if ($this->defaultHostHeader && !self::isHeaderSet('Host', $headers)) {
            $headers['Host'] = $this->defaultHostHeader;
        }

        return new Request($method, $endpoint, $headers, $body);
    }

    private static function isHeaderSet(string $header, array $headers): bool
    {
        foreach (array_keys($headers) as $name) {
            if (strtolower((string) $name) === strtolower($header)) {
                return true;
            }
        }

        return false;
    }
}
