<?php

namespace Keboola\Juicer\Pagination\Decorator;

use Keboola\Juicer\Client\RestClient;
use Keboola\Juicer\Client\RestRequest;
use Keboola\Juicer\Pagination\ScrollerInterface;
use Keboola\Juicer\Config\JobConfig;
use Keboola\Juicer\Exception\UserException;

/**
 * Adds 'nextPageFlag' option to look at a boolean
 * field in response to continue/stop scrolling
 * config:
 * pagination:
 *   nextPageFlag:
 *     field: hasMore #name of the bool field
 *     stopOn: false #whether to stop once the value is true or false
 *     ifNotSet: false #optional, what value to assume if the field is not present
 */
class HasMoreScrollerDecorator extends AbstractScrollerDecorator
{
    protected $nextPageFlag = null;

    public function __construct(ScrollerInterface $scroller, array $config)
    {
        if (!empty($config['nextPageFlag'])) {
            if (empty($config['nextPageFlag']['field'])) {
                throw new UserException("'field' has to be specified for 'nextPageFlag'");
            }

            if (!isset($config['nextPageFlag']['stopOn'])) {
                throw new UserException("'stopOn' value must be set to a boolean value for 'nextPageFlag'");
            }

            if (!isset($config['nextPageFlag']['ifNotSet'])) {
                $config['nextPageFlag']['ifNotSet'] = $config['nextPageFlag']['stopOn'];
            }

            $this->nextPageFlag = $config['nextPageFlag'];
        }

        parent::__construct($scroller, $config);
    }

    /**
     * @param RestClient $client
     * @param $jobConfig $jobConfig
     * @param mixed $response
     * @param array $data
     * @return RestRequest|false
     */
    public function getNextRequest(RestClient $client, JobConfig $jobConfig, $response, $data)
    {
        if (false === $this->hasMore($response)) {
            return false;
        }

        return $this->scroller->getNextRequest($client, $jobConfig, $response, $data);
    }

    /**
     * @param mixed $response
     * @return bool|null Returns null if this option isn't used
     */
    protected function hasMore($response)
    {
        if (empty($this->nextPageFlag)) {
            return null;
        }

        if (!isset($response->{$this->nextPageFlag['field']})) {
            $value = $this->nextPageFlag['ifNotSet'];
        } else {
            $value = $response->{$this->nextPageFlag['field']};
        }

        if ((bool) $value === $this->nextPageFlag['stopOn']) {
            return false;
        } else {
            return true;
        }
    }
}
