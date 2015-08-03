<?php

namespace Keboola\Juicer\Client;

use	Keboola\Juicer\Exception\UserException,
	Keboola\Juicer\Exception\ApplicationException,
	Keboola\Juicer\Config\JobConfig,
	Keboola\Juicer\Common\Logger;
use	GuzzleHttp\Client,
	GuzzleHttp\Exception\BadResponseException,
	GuzzleHttp\Exception\ClientException,
	GuzzleHttp\Message\Request as GuzzleRequest,
	GuzzleHttp\Subscriber\Retry\RetrySubscriber,
	GuzzleHttp\Event\AbstractTransferEvent,
	GuzzleHttp\Event\ErrorEvent;
use	Keboola\Utils\Utils;

/**
 *
 */
class RestClient
{
	/**
	 * @var Client
	 */
	protected $client;

	/**
	 * GET or POST
	 * @var string
	 */
	protected $method;

	public function __construct(Client $guzzle)
	{
		$this->client = $guzzle;
	}

	public static function create($defaults = [])
	{
		$guzzle = new Client($defaults);
		$guzzle->getEmitter()->attach(self::getBackoff());
		return new self($guzzle);
	}

	/**
	 * @param Request $request
	 * @return \GuzzleHttp\Message\Response
	 */
	public function download(Request $request)
	{
		try {
			$response = $this->client->send($this->getGuzzleRequest($request));
		} catch (BadResponseException $e) {
			// TODO try XML if JSON fails
			$data = json_decode($e->getResponse()->getBody(), true);
			if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
				$data = (string) $e->getResponse()->getBody();
			}

			throw new UserException(
				"The API request failed: [" . $e->getResponse()->getStatusCode() . "] " . $e->getMessage(),
				400,
				$e,
				['body' => $data]
			);
		}

		return $response;
	}

	protected function getGuzzleRequest(Request $request)
	{
		if (!$request instanceof RestRequest) {
			throw new ApplicationException("RestClient requires a RestRequest!");
		}

		switch ($request->getMethod()) {
			case 'GET':
				$method = $request->getMethod();
				$endpoint = Utils::buildUrl($request->getEndpoint(), $request->getParams());
				$options = [];
				break;
			case 'POST':
				$method = $request->getMethod();
				$endpoint = $request->getEndpoint();
				$options = ['json' => $request->getParams()];
				break;
			case 'FORM':
				$method = 'POST';
				$endpoint = $request->getEndpoint();
				$options = ['body' => $request->getParams()];
				break;
			default:
				throw new UserException("Unknown request method '" . $request->getMethod() . "' for '" . $request->getEndpoint() . "'");
				break;

		}

		return $this->client->createRequest($method, $endpoint, $options);
	}

	public function getRequest(JobConfig $jobConfig)
	{
		return RestRequest::create($jobConfig->getConfig());
	}


	/**
	 * Returns an exponential backoff (prefers Retry-After header) for GuzzleClient (4.*).
	 * Use: `$client->getEmitter()->attach($this->getBackoff());`
	 * @param int $max
	 * @return RetrySubscriber
	 */
	public static function getBackoff($max = 8, $retryCodes = [500, 502, 503, 504, 408, 420, 429])
	{
		return new RetrySubscriber([
			'filter' => RetrySubscriber::createChainFilter([
				RetrySubscriber::createStatusFilter($retryCodes),
				RetrySubscriber::createCurlFilter()
			]),
			'max' => $max,
			'delay' => function ($retries, AbstractTransferEvent $event) {
				if (!is_null($event->getResponse()) && $event->getResponse()->hasHeader('Retry-After')) {
					$retryAfter = $event->getResponse()->getHeader('Retry-after');
					if (is_numeric($retryAfter) && $retryAfter < 1417200713) {
						$delay =  $retryAfter;
					} elseif (Utils::isValidDateTimeString($retryAfter, DATE_RFC1123)) {
						// why not strtotime()?
						$date = \DateTime::createFromFormat(DATE_RFC1123, $retryAfter);
						$delay = $date->getTimestamp() - time();
					} else {
						$delay  = RetrySubscriber::exponentialDelay($retries, $event);
					}
				} else {
					$delay  = RetrySubscriber::exponentialDelay($retries, $event);
				}

				$errData = [
					"http_code" => $event->getTransferInfo()['http_code'],
					"body" => is_null($event->getResponse()) ? null : (string) $event->getResponse()->getBody(),
					"url" =>  $event->getTransferInfo()['url'],
				];
				if ($event instanceof ErrorEvent) {
					$errData["message"] = $event->getException()->getMessage();
				}
				Logger::log("DEBUG", "Http request failed, retrying in {$delay}s", $errData);

				// ms > s
				return 1000 * $delay;
			}
		]);
	}
}
