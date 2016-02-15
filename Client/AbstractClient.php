<?php

namespace Keboola\Juicer\Client;

/**
 *
 */
abstract class AbstractClient
{
    /**
     * @var array
     */
    protected $defaultRequestOptions = [];

    /**
     * {@inheritdoc}
     */
    public function setDefaultRequestOptions(array $options)
    {
        $this->defaultRequestOptions = $options;
    }

    /**
     * Update request config with default options
     * @param array $config
     * @return array
     */
    protected function getRequestConfig(array $config)
    {
        if (!empty($this->defaultRequestOptions)) {
            $config = array_replace_recursive($this->defaultRequestOptions, $config);
        }

        return $config;
    }
}
