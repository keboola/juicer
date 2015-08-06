<?php

namespace Keboola\Juicer\Extractor;

use	GuzzleHttp\Client as GuzzleClient;
use	Keboola\Utils\Utils;
use	Keboola\Juicer\Common\Logger,
	Keboola\Juicer\Config\JobConfig,
	Keboola\Juicer\Client\ClientInterface,
	Keboola\Juicer\Client\RequestInterface,
	Keboola\Juicer\Parser\ParserInterface;
use	Keboola\Juicer\Exception\UserException;
/**
 * A generic Job class generally used to set up each API call, handle its pagination and parsing into a CSV ready for SAPI upload
 */
abstract class Job
{
	/** @var JobConfig */
	protected $config;
	protected $client;
	protected $parser;
	/** @var double */
	protected $startTime;
	/** @var string */
	protected $jobId;

	/**
	 * @param JobConfig $config
	 * @param ClientInterface $client A client used to communicate with the API (wrapper for Guzzle, SoapClient, ...)
	 * @param ParserInterface $parser A parser to handle the result and convert it into CSV file(s)
	 */
	public function __construct(JobConfig $config, ClientInterface $client, ParserInterface $parser)
	{
		$this->config = $config;
		$this->client = $client;
		$this->parser = $parser;
		$this->jobId = $config->getJobId();

		$this->startTime = microtime(true);
	}

	/**
	 *  Usually handles the standard procedure.
	 * @example:
	 *	public function run() {
	 *		$request = $this->firstPage();	// Obtain a request for the first API call
	 *		while ($request !== false) {	// Fail if a request for another page hasn't been returned
	 *			$response = $this->download($request);	// Download (and xml/json_decode by default - see RestJob/SoapJob::download())
	 *			$this->parse($response);	// Use the parser/handle on your own
	 *			$request = $this->nextPage($response);	// Generate a new request OR false if finished
	 *		}
	 *	}
	 *
	 * @return void
	 */
	public function run()
	{
		$request = $this->firstPage($this->config);
		while ($request !== false) { // TODO !empty sounds better, doesn't it? Perhaps it's lazy?
			$response = $this->download($request);
			$data = $this->parse($response);
			$request = $this->nextPage($this->config, $response, $data);
		}
	}

	/**
	 *  Download an URL from REST or SOAP API and return its body as an object.
	 * should handle the API call, backoff and response decoding
	 *
	 * @param RequestInterface $request
	 * @return \StdClass $response
	 */
	protected function download(RequestInterface $request)
	{
		$this->client->download($request);
	}

	/**
	 * Parse the result into a CSV (either using any of built-in parsers, or using own methods).
	 *
	 * @param object $response
	 * @return array|mixed the unparsed data array
	 */
	protected function parse($response)
	{
		$data = $this->findDataInResponse($response, $this->config->getConfig());
		$this->parser->process($data);
	}

	/**
	 * Create subsequent requests for pagination (usually based on $response from previous request)
	 * Return a download request OR false if no next page exists
	 *
	 * @param JobConfig $config
	 * @param mixed $response
	 * @param array|null $data
	 * @return \Keboola\Juicer\Client\SoapRequest | \GuzzleHttp\Message\Request | ... | false
	 */
	protected function nextPage(JobConfig $config, $response, $data)
	{

		return false;
	}

	/**
	 * Create the first download request.
	 * Return a download request
	 *
	 * @param JobConfig $config
	 * @return \Keboola\Juicer\Client\SoapRequest | \GuzzleHttp\Message\Request | ... | false
	 * @todo abstract?
	 */
	protected function firstPage(JobConfig $config)
	{
		return $this->client->getRequest($config);
	}

	/**
	 *  In case the request has ie. expiry time.
	 * TODO use as a callback function instead?
	 * FIXME no longer works in RestJob
	 *
	 * @param &$request
	 * @return void
	 */
	protected function updateRequest($request) {}

	/**
	 * Try to find the data array within $response.
	 *
	 * @param array|object $response
	 * @param array $config
	 * @return array
	 */
	protected function findDataInResponse($response, array $config = [])
	{
		// If dataField doesn't say where the data is in a response, try to find it!
		if (!empty($config['dataField'])) {
			$data = Utils::getDataFromPath($config['dataField'], $response, ".");
			// In case of a single object being returned
			if (!is_array($data)) {
				$data = [$data];
			}
		} elseif (is_array($response)) {
			// Simplest case, the response is just the dataset
			$data = $response;
		} elseif (is_object($response)) {
			// Find arrays in the response
			$arrays = [];
			foreach($response as $key => $value) {
				if (is_array($value)) {
					$arrays[$key] = $value;
				} // TODO else {$this->metadata[$key] = json_encode($value);} ? return [$data,$metadata];
			}

			$arrayNames = array_keys($arrays);
			if (count($arrays) == 1) {
				$data = $arrays[$arrayNames[0]];
			} elseif (count($arrays) == 0) {
				Logger::log('warning', "No data array found in response! (endpoint: {$config['endpoint']})", [
					'response' => json_encode($response),
					'config row ID' => $this->getJobId()
				]);
				$data = [];
			} else {
				$e = new UserException("More than one array found in response! Use 'dataField' parameter to specify a key to the data array. (endpoint: {$config['endpoint']}, arrays in response root: " . join(", ", $arrayNames) . ")");
				$e->setData([
					'response' => json_encode($response),
					'config row ID' => $this->getJobId(),
					'arrays found' => $arrayNames
				]);
				throw $e;
			}
		} else {
			$e = new UserException('Unknown response from API.');
			$e->setData([
				'response' => json_encode($response),
				'config row ID' => $this->getJobId()
			]);
			throw $e;
		}

		return $data;
	}

	/**
	 *  Return an array of generated files to upload to Sapi after the job is finished: array(table_name => CsvFile).
	 *
	 * @return \Keboola\Csv\CsvFile[]
	 * @deprecated DYI!
	 */
	public function getCsvList()
	{
		return array();
	}

	/**
	 * Returns time elapsed since initializing the Job.
	 * @return double
	 */
	public function getRunTime()
	{
		return microtime(true) - $this->startTime;
	}

	/**
	 * @return string
	 */
	public function getJobId()
	{
		return $this->jobId;
	}
}
