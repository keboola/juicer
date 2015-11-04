<?php

namespace Keboola\Juicer\Extractor;

use GuzzleHttp\Client as GuzzleClient;
use Keboola\Utils\Utils;
use Keboola\Juicer\Common\Logger,
    Keboola\Juicer\Config\JobConfig,
    Keboola\Juicer\Client\ClientInterface,
    Keboola\Juicer\Client\RequestInterface,
    Keboola\Juicer\Parser\ParserInterface,
    Keboola\Juicer\Pagination\ScrollerInterface,
    Keboola\Juicer\Pagination\NoScroller;
use Keboola\Juicer\Exception\UserException;
/**
 * A generic Job class generally used to set up each API call, handle its pagination and parsing into a CSV ready for SAPI upload
 */
class Job
{
    /**
     * @var JobConfig
     */
    protected $config;
    /**
     * @var ClientInterface
     */
    protected $client;
    /**
     * @var ParserInterface
     */
    protected $parser;
    /**
     * @var ScrollerInterface
     */
    protected $scroller;
    /**
     * @var string
     */
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
    }

    /**
     * Manages cycling through the requests as long as
     * scroller provides next page
     *
     * @return void
     */
    public function run()
    {
        $request = $this->firstPage($this->config);
        while ($request !== false) {
            $response = $this->download($request);
            $data = $this->findDataInResponse($response, $this->config->getConfig());
            $this->parse($data);
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
        return $this->client->download($request);
    }

    /**
     * Parse the result into a CSV (either using any of built-in parsers, or using own methods).
     *
     * @param object $response
     * @param array $parentId ID (or list thereof) to be passed to parser
     */
    protected function parse(array $data, array $parentId = null)
    {
        $this->parser->process($data, $this->getDataType(), $parentId);
    }

    protected function getDataType()
    {
        $config = $this->config->getConfig();
        $type = !empty($config['dataType'])
            ? $config['dataType']
            : $config['endpoint'];
        return $type;
    }

    /**
     * Create subsequent requests for pagination (usually based on $response from previous request)
     * Return a download request OR false if no next page exists
     *
     * @param JobConfig $config
     * @param mixed $response
     * @param array|null $data
     * @return RequestInterface | false
     */
    protected function nextPage(JobConfig $config, $response, $data)
    {
        return $this->getScroller()->getNextRequest($this->client, $config, $response, $data);
    }

    /**
     * Create the first download request.
     * Return a download request
     *
     * @param JobConfig $config
     * @return RequestInterface | false
     */
    protected function firstPage(JobConfig $config)
    {
        return $this->getScroller()->getFirstRequest($this->client, $config);
    }

    /**
     *  In case the request has ie. expiry time.
     * TODO use as a callback function instead? in second parameter to download
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
     * @todo support array of dataFields
     *     - would return object with results, changing the class' API
     *     - parse would just have to loop through if it returns an object
     *     - and append $type with the dataField
     */
    protected function findDataInResponse($response, array $config = [])
    {
        // If dataField doesn't say where the data is in a response, try to find it!
        if (!empty($config['dataField'])) {
            if (is_array($config['dataField'])) {
                if (empty($config['dataField']['path'])) {
                    throw new UserException("'dataField.path' must be set!");
                }

                $path = $config['dataField']['path'];
            } elseif (is_scalar($config['dataField'])) {
                $path = $config['dataField'];
            } else {
                throw new UserException("'dataField' must be either a path string or an object with 'path' attribute.");
            }

            $data = Utils::getDataFromPath($path, $response, ".");
            if (empty($data)) {
                Logger::log('warning', "dataField '{$path}' contains no data!");
            }

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
     * @return string
     */
    public function getJobId()
    {
        return $this->jobId;
    }

    /**
     * @return ScrollerInterface
     */
    protected function getScroller()
    {
        if (empty($this->scroller)) {
            $this->scroller = new NoScroller;
        }

        return $this->scroller;
    }

    /**
     * @param ScrollerInterface $scroller
     */
    public function setScroller(ScrollerInterface $scroller)
    {
        $this->scroller = $scroller;
    }
}
