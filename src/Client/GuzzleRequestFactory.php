<?php

declare(strict_types=1);

namespace Keboola\Juicer\Client;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Utils;
use Keboola\Juicer\Exception\UserException;
use Psr\Http\Message\RequestInterface;
use function Keboola\Utils\buildUrl;

/**
 * Creates GuzzleRequest from RestRequest
 */
class GuzzleRequestFactory
{
    public function create(RestRequest $restRequest): RequestInterface
    {
        $body = null;
        switch ($restRequest->getMethod()) {
            case 'GET':
                $method = $restRequest->getMethod();
                $endpoint = buildUrl($restRequest->getEndpoint(), $restRequest->getParams());
                break;
            case 'POST':
                $method = $restRequest->getMethod();
                $endpoint = $restRequest->getEndpoint();
                $body = Utils::jsonEncode($restRequest->getParams());
                break;
            case 'FORM':
                $method = 'POST';
                $endpoint = $restRequest->getEndpoint();
                $body = \http_build_query($restRequest->getParams(), '', '&');
                break;
            default:
                throw new UserException(sprintf(
                    "Unknown request method '%s' for '%s'",
                    $restRequest->getMethod(),
                    $restRequest->getEndpoint()
                ));
        }

        return new Request($method, $endpoint, $restRequest->getHeaders(), $body);
    }
}
