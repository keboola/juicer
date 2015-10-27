<?php

namespace Keboola\Juicer\Client;

use    Keboola\Juicer\Config\JobConfig;

/**
 *
 */
interface ClientInterface
{
    /**
     * @param RequestInterface $request
     * @return mixed Raw response as it comes from the client
     */
    public function download(RequestInterface $request);


    /**
     * Create a request from a JobConfig->getConfig() array
     * [
     *    'endpoint' => 'resource', // Required
     *    'params' => [
     *        'some' => 'parameter'
     *    ],
     *    'method' => 'GET', // REST only
     *    'options' => [], // SOAP only
     *    'inputHeader' => '' // SOAP only
     * ]
     * @param array $config
     * @return RequestInterface
     */
    public function createRequest(array $config);

    /**
     *
     */
    public function getClient();
}
