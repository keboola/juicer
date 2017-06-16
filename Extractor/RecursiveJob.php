<?php

namespace Keboola\Juicer\Extractor;

use Keboola\Juicer\Config\JobConfig;
use Keboola\Juicer\Client\ClientInterface;
use Keboola\Juicer\Parser\ParserInterface;
use Keboola\Juicer\Exception\UserException;
use Keboola\Filter\Filter;
use Keboola\Filter\Exception\FilterException;
use Keboola\Utils\Exception\NoDataFoundException;
use Keboola\Code\Builder;
use Psr\Log\LoggerInterface;

/**
 * {@inheritdoc}
 * Adds a capability to process recursive calls based on
 * responses.
 * If an endpoint column in config table contains {} enclosed
 * parameter, it'll be replaced by a value from a parent call
 * based on the values from its response and "mapping" set in
 * child's "placeholders" object
 * Expects the configuration to use 'endpoint' column to store
 * the API endpoint
 */
abstract class RecursiveJob extends Job implements RecursiveJobInterface
{
    /**
     * Used to save necessary parents' data to child's output
     * @var array
     */
    protected $parentParams = [];

    /**
     * @var array
     */
    protected $parentResults = [];

    /**
     * {@inheritdoc}
     */
    public function __construct(JobConfig $config, ClientInterface $client, ParserInterface $parser, LoggerInterface $logger)
    {
        parent::__construct($config, $client, $parser, $logger);
        // If no dataType is set, save endpoint as dataType before replacing placeholders
        if (empty($this->config->getConfig()['dataType']) && !empty($this->config->getConfig()['endpoint'])) {
            $this->config->setDataType($this->getDataType());
        }
    }

    /**
     * Add parameters from parent call to the Endpoint.
     * The parameter name in the config's endpoint has to be enclosed in {}
     * @param array $params
     */
    public function setParams(array $params)
    {
        foreach ($params as $param) {
            $this->config->setEndpoint(str_replace('{' . $param['placeholder'] . '}', $param['value'], $this->config->getConfig()['endpoint']));
        }

        $this->parentParams = $params;
    }

    /**
     * @param string $string
     * @return string
     */
    protected function prependParent($string)
    {
        return (substr($string, 0, 7) == "parent_") ? $string : "parent_{$string}";
    }

    /**
     * Create subsequent jobs for recursive endpoints
     * Uses "children" section of the job config
     * {@inheritdoc}
     */
    protected function parse(array $data, array $parentId = null)
    {
        parent::parse($data, $this->getParentCols($parentId));

        $this->runChildJobs($data);

        return $data;
    }

    /**
     * @param array $data
     * @throws UserException
     */
    protected function runChildJobs(array $data)
    {
        foreach ($this->config->getChildJobs() as $jobId => $child) {
            if (!empty($child->getConfig()['recursionFilter'])) {
                try {
                    $filter = Filter::create($child->getConfig()['recursionFilter']);
                } catch (FilterException $e) {
                    throw new UserException($e->getMessage(), 0, $e);
                }
            }

            foreach ($data as $result) {
                if (!empty($filter) && ($filter->compareObject((object) $result) == false)) {
                    continue;
                }

                // Add current result to the beginning of an array, containing all parent results
                $parentResults = $this->parentResults;
                array_unshift($parentResults, $result);

                $childJob = $this->createChild($child, $parentResults);
                $childJob->run();
            }
        }
    }

    /**
     * @param array $parentIdCols
     * @return array
     */
    protected function getParentCols(array $parentIdCols = null)
    {
        // Add parent values to the result
        $parentCols = is_null($parentIdCols) ? [] : $parentIdCols;
        foreach ($this->parentParams as $v) {
            $key = $this->prependParent($v['field']);
            $parentCols[$key] = $v['value'];
        }
        return $parentCols;
    }

    /**
     * Create a child job with current client and parser
     * @param JobConfig $config
     * @return static
     */
    protected function createChild(JobConfig $config, array $parentResults)
    {
        // Clone the config to prevent overwriting the placeholder(s) in endpoint
        $job = new static(clone $config, $this->client, $this->parser, $this->logger);

        $params = [];
        $placeholders = !empty($config->getConfig()['placeholders']) ? $config->getConfig()['placeholders'] : [];
        if (empty($placeholders)) {
            $this->logger->warning("No 'placeholders' set for '" . $config->getConfig()['endpoint'] . "'");
        }

        foreach ($placeholders as $placeholder => $field) {
            $params[$placeholder] = $this->getPlaceholder($placeholder, $field, $parentResults);
        }

        // Add parent params as well (for 'tagging' child-parent data)
        // Same placeholder in deeper nesting replaces parent value
        if (!empty($this->parentParams)) {
            $params = array_replace($this->parentParams, $params);
        }

        $job->setParams($params);
        $job->setParentResults($parentResults);

        return $job;
    }

    /**
     * @param string $placeholder
     * @param string|object|array $field Path or a function with a path
     * @param $parentResults
     * @return array ['placeholder', 'field', 'value']
     * @throws UserException
     */
    protected function getPlaceholder($placeholder, $field, $parentResults)
    {
        // TODO allow using a descriptive ID(level) by storing the result by `task(job) id` in $parentResults
        $level = strpos($placeholder, ':') === false
            ? 0
            : strtok($placeholder, ':') -1;

        if (!is_scalar($field)) {
            if (empty($field['path'])) {
                throw new UserException("The path for placeholder '{$placeholder}' must be a string value or an object containing 'path' and 'function'.");
            }

            $fn = \Keboola\Utils\arrayToObject($field);
            $field = $field['path'];
            unset($fn->path);
        }

        $value = $this->getPlaceholderValue($field, $parentResults, $level, $placeholder);

        if (isset($fn)) {
            $builder = new Builder;
            $builder->allowFunction('urlencode');
            $value = $builder->run($fn, ['placeholder' => ['value' => $value]]);
        }

        return [
            'placeholder' => $placeholder,
            'field' => $field,
            'value' => $value
        ];
    }

    /**
     * @param string $field
     * @param array $parentResults
     * @param int $level
     * @param string $placeholder
     * @return mixed
     * @throws UserException
     */
    protected function getPlaceholderValue($field, $parentResults, $level, $placeholder)
    {
        try {
            if (!array_key_exists($level, $parentResults)) {
                $maxLevel = empty($parentResults) ? 0 : max(array_keys($parentResults)) +1;
                throw new UserException("Level " . ++$level . " not found in parent results! Maximum level: " . $maxLevel);
            }

            return \Keboola\Utils\getDataFromPath($field, $parentResults[$level], ".", false);
        } catch (NoDataFoundException $e) {
            throw new UserException(
                "No value found for {$placeholder} in parent result. (level: " . ++$level . ")",
                0,
                null,
                [
                    'parents' => $parentResults
                ]
            );
        }
    }

    public function setParentResults(array $results)
    {
        $this->parentResults = $results;
    }
}
