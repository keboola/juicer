<?php

namespace Keboola\Juicer\Client;

use Keboola\Juicer\Exception\UserException,
    Keboola\Juicer\Exception\ApplicationException,
    Keboola\Juicer\Config\JobConfig,
    Keboola\Juicer\Common\Logger;
use Keboola\Utils\Utils;
use SoapClient;

/**
 *
 */
class SoapClient implements ClientInterface
{
    /**
     * @var SoapClient
     */
    protected $client;

    /**
     * @var int
     */
    protected $backoffTryCount;

    public function __construct(SoapClient $client, $backoffTryCount = 8)
    {
        $this->client = $client;
        $this->backoffTryCount = $backoffTryCount;
    }

    public static function create($wsdl = null, $options = [])
    {
        return new self(SoapClient($wsdl, $options));
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function download(RequestInterface $request)
    {
        $backoffTry = 0;
        $response = null;
        do {
            if ($backoffTry > 0) {
                sleep(pow(2, $backoffTry));
            }

            // TODO refresh request may come here

            try {
                $response = $this->client->__soapCall($request->getFunction(), $request->getParams(), $request->getOptions(), $request->getInputHeader(), $outputHeaders);
            } catch(\SoapFault $e) {
                $backoffTry++;
                    $errData = array(
                        "code" => $e->getCode(),
                        "message" => $e->getMessage(),
                        "faultcode" => isset($e->faultcode) ? $e->faultcode : null,
                        "faultstring" => isset($e->faultstring) ? $e->faultstring : null,
                        "detail" => isset($e->detail) ? ((array) $e->detail) : null,
                    );

                // Do not retry if max. retry count is reached OR the error isn't on server(TODO?):  || $errData["faultcode"] == "SOAP-ENV:Client"
                if ($backoffTry >= $this->backoffTryCount) {
                    $e = new UserException("Soap call failed:" . $e->getCode() . ": " . $e->getMessage(), 400, $e);
                    $e->setData($errData);
                    throw $e;
                } else {
                    Logger::log("debug", "Soap call error, retrying:" . $e->getCode() . ": " . $e->getMessage(), $errData);
                }
            }
        } while ($response === null);

        return $response;
    }

    public function createRequest(array $config)
    {
        if (!empty($this->defaultRequestOptions)) {
            $config = array_replace_recursive($this->defaultRequestOptions, $config);
        }

        return SoapRequest::create($config);
    }

    /**
     * @return client
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * {@inheritdoc}
     */
    public function setDefaultRequestOptions(array $options)
    {
        $this->defaultRequestOptions = $options;
    }
}
