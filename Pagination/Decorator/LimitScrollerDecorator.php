<?php

namespace Keboola\Juicer\Pagination\Decorator;

use Keboola\Juicer\Client\RestClient;
use Keboola\Juicer\Client\RestRequest;
use Keboola\Juicer\Exception\UserException;
use Keboola\Juicer\Pagination\ScrollerInterface;
use Keboola\Juicer\Config\JobConfig;

/**
 * Class LimitScrollerDecorator
 * Adds 'limit' option
 */
class LimitScrollerDecorator extends AbstractScrollerDecorator
{
    /**
     * @var int
     */
    private $countLimit;

    /**
     * @var string
     */
    private $fieldName;

    /**
     * @var int
     */
    private $currentCount;

    /**
     * Constructor.
     * @param ScrollerInterface $scroller
     * @param array $config
     * @throws UserException
     */
    public function __construct(ScrollerInterface $scroller, array $config)
    {
        parent::__construct($scroller);
        if (!empty($config['limit'])) {
            if (empty($config['limit']['field']) && empty($config['limit']['count'])) {
                throw new UserException("One of 'limit.field' or 'limit.count' attributes is required.");
            }
            if (!empty($config['limit']['field']) && !empty($config['limit']['count'])) {
                throw new UserException("Specify only one of 'limit.field' or 'limit.count'.");
            }
            if (!empty($config['limit']['field'])) {
                $this->fieldName = $config['limit']['field'];
            }
            if (!empty($config['limit']['count'])) {
                $this->countLimit = intval($config['limit']['count']);
            }
        }
        $this->reset();
    }

    /**
     * @param RestClient $client
     * @param $jobConfig $jobConfig
     * @return RestRequest
     */
    public function getFirstRequest(RestClient $client, JobConfig $jobConfig)
    {
        $this->currentCount = 0;
        return $this->scroller->getFirstRequest($client, $jobConfig);
    }

    /**
     * @inheritdoc
     */
    public function getNextRequest(RestClient $client, JobConfig $jobConfig, $response, $data)
    {
        $this->currentCount += count($data);
        if ($this->fieldName) {
            $limit = \Keboola\Utils\getDataFromPath($this->fieldName, $response, '.');
        } else {
            $limit = $this->countLimit;
        }
        if ($this->currentCount >= $limit) {
            return false;
        }

        return $this->scroller->getNextRequest($client, $jobConfig, $response, $data);
    }

    /**
     * @inheritdoc
     */
    public function reset()
    {
        $this->currentCount = 0;
        parent::reset();
    }
}
