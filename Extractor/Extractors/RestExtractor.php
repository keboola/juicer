<?php

namespace Keboola\ExtractorBundle\Extractor\Extractors;

use	Keboola\ExtractorBundle\Extractor\Extractor;

use GuzzleHttp\Subscriber\Retry\RetrySubscriber,
	GuzzleHttp\Event\AbstractTransferEvent;

use	Keboola\ExtractorBundle\Common\Logger;

use Keboola\Utils\Utils;

/**
 * {@inheritdoc}
 * Adds a getBackoff function for GuzzleHttp.
 */
abstract class RestExtractor extends Extractor {
	/**
	 * Returns an exponential backoff (prefers Retry-After header) for GuzzleClient (4.*).
	 * Use: `$client->getEmitter()->attach($this->getBackoff());`
	 * @param int $max
	 * @return RetrySubscriber
	 */
	protected function getBackoff($max = 8, $retryCodes = [500, 502, 503, 504, 408, 420, 429])
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
				if ($event instanceof \GuzzleHttp\Event\ErrorEvent) {
					$errData["message"] = $event->getException()->getMessage();
				}
				Logger::log("DEBUG", "Http request failed, retrying in {$delay}s", $errData);

				// ms > s
				return 1000 * $delay;
			}
		]);
	}
}
