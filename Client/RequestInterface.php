<?php

namespace Keboola\Juicer\Client;

interface RequestInterface
{
    public function getEndpoint();

    public function getParams();

    public static function create(array $config);
}
