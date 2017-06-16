<?php

namespace Keboola\Juicer\Extractor;

use Keboola\Juicer\Config\JobConfig;
use Keboola\Juicer\Client\ClientInterface;
use Keboola\Juicer\Client\RequestInterface;
use Keboola\Juicer\Pagination\ScrollerInterface;
use Keboola\Juicer\Parser\ParserInterface;
use Psr\Log\LoggerInterface;

/**
 * A generic Job class generally used to set up each API call, handle its pagination and parsing into a CSV ready for SAPI upload
 */
abstract class Job
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
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param JobConfig $config
     * @param ClientInterface $client A client used to communicate with the API (wrapper for Guzzle)
     * @param ParserInterface $parser A parser to handle the result and convert it into CSV file(s)
     * @param LoggerInterface $logger
     */
    public function __construct(JobConfig $config, ClientInterface $client, ParserInterface $parser, LoggerInterface $logger)
    {
        $this->logger = $logger;
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
    abstract public function run();

    /**
     * Create the first download request.
     * Return a download request
     *
     * @param JobConfig $config
     * @return RequestInterface | false
     */
    abstract protected function firstPage(JobConfig $config);

    /**
     * Create subsequent requests for pagination (usually based on $response from previous request)
     * Return a download request OR false if no next page exists
     *
     * @param JobConfig $config
     * @param mixed $response
     * @param array|null $data
     * @return RequestInterface | false
     */
    abstract protected function nextPage(JobConfig $config, $response, $data);

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
     * @param array $data
     * @param array $parentId ID (or list thereof) to be passed to parser
     */
    protected function parse(array $data, array $parentId = null)
    {
        $this->parser->process($data, $this->getDataType(), $parentId);
    }

    /**
     * @return string
     */
    protected function getDataType()
    {
        $config = $this->config->getConfig();
        $type = !empty($config['dataType'])
            ? $config['dataType']
            : $config['endpoint'];
        return $type;
    }

    /**
     * @return string
     */
    public function getJobId()
    {
        return $this->jobId;
    }
}
