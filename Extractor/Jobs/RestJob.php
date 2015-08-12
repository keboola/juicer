<?php

namespace Keboola\Juicer\Extractor\Jobs;

use	Keboola\Juicer\Exception\ApplicationException,
	Keboola\Juicer\Exception\UserException;
use	Keboola\Juicer\Extractor\Job,
	Keboola\Juicer\Config\JobConfig,
	Keboola\Juicer\Common\Logger,
	Keboola\Juicer\Client\RestClient;
use	Keboola\Utils\Utils;
use	GuzzleHttp\Client as GuzzleClient,
	GuzzleHttp\Exception\BadResponseException,
	GuzzleHttp\Exception\ClientException;
/**
 * A Job to handle download using Guzzle 4 client
 * It is recommended to use the following columns to
 * describe the request:
 * "endpoint": the API endpoint
 * "params": JSON encoded list of parameters such as query
 * @deprecated
 */
abstract class RestJob extends Job
{
	// override if the server response isn't UTF-8
	protected $responseEncoding = 'UTF-8';

	const JSON = 'json';
	const XML = 'xml';
	const RAW = 'raw';

	/**
	 * @var GuzzleClient
	 */
	protected $client;

	/**
	 * {@inheritdoc}
	 */
	public function __construct(JobConfig $config, $client, $parser = null) {
		if (!($client instanceof GuzzleClient)) {
			throw new ApplicationException('$client must be an instance of GuzzleHttp\Client! "' . get_class($client) . '" provided');
		}

		parent::__construct($config, $client, $parser);
	}

	/**
	 * Download an URL from REST API and return its body as an associative array
	 * TODO a fn that'll call a download function in a "DownloaderInterface" and return raw response.
	 * - downloader replaces client..or IS client, just needs to implement an interface from ex-bundle
	 * TODO a "ParserInterface" that'll offer wrappers for parsers
	 * TODO rest downloader(client) could use POST as well
	 *
	 * @param \GuzzleHttp\Message\Request $request
	 * @param string $format Format of source data to decode, OR 'raw' to return the response body as is
	 * @return object - json_decoded response body
	 */
	protected function download($request, $format = self::JSON)
	{
		$downloader = new RestClient($this->client, 'GET');
		$response = $downloader->download($request);

		// Format the response
		switch ($format) {
			case self::JSON:
				// TODO this should be configurable in services.yml or where? Perhaps SAPI config, just not available in UI. Ever.
				$maxJsonSize = 10e6;
				if($response->hasHeader("Content-Length") && ($response->getHeader("Content-Length") > $maxJsonSize)) {
					throw new UserException("[509] The server response is too large!");
				}

				// Sanitize the JSON
				$body = iconv($this->responseEncoding, 'UTF-8//IGNORE', $response->getBody());
				return Utils::json_decode($body);
			case self::XML:
				try {
					$xml = new \SimpleXMLElement($response->getBody());
				} catch(\Exception $e) {
					throw new UserException(
						"Error decoding the XML response: " . $e->getMessage(),
						400,
						$e,
						['body' => (string) $response->getBody()]
					);
				}
				return $xml;
			case self::RAW:
				return (string) $response->getBody();
			default:
				throw new ApplicationException("Data format {$format} not supported.");
		}
	}
}
