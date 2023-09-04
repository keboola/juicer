<?php

declare(strict_types=1);

namespace Keboola\Juicer\Pagination\Decorator;

use Keboola\Juicer\Client\RestClient;
use Keboola\Juicer\Client\RestRequest;
use Keboola\Juicer\Config\JobConfig;
use Keboola\Juicer\Exception\UserException;
use Keboola\Juicer\Pagination\ScrollerInterface;

/**
 * Class HasMoreScrollerDecorator
 * Adds 'nextPageFlag' option to look at a boolean field in response to continue/stop scrolling
 */
class HasMoreScrollerDecorator extends AbstractScrollerDecorator
{
    protected ?string $field = null;

    protected bool $stopOn = false;

    protected bool $ifNotSet = false;

    /**
     * HasMoreScrollerDecorator constructor.
     * @param array $config array with `nextPageFlag` item which is:
     *      [
     *          'field' => string // name of the boolean field
     *          'stopOn' => bool // whether to stop if the field value is true or false
     *          'ifNotSet' => bool // what value to assume if the field is not present
     *      ]
     * @throws UserException
     */
    public function __construct(ScrollerInterface $scroller, array $config)
    {
        if (!empty($config['nextPageFlag'])) {
            if (empty($config['nextPageFlag']['field'])) {
                throw new UserException("'field' has to be specified for 'nextPageFlag'");
            }

            if (!isset($config['nextPageFlag']['stopOn'])) {
                throw new UserException("'stopOn' value must be set to a boolean value for 'nextPageFlag'");
            }

            if (!is_bool($config['nextPageFlag']['stopOn'])) {
                throw new UserException(sprintf(
                    "'stopOn' value must be set to a boolean value for 'nextPageFlag', given '%s' type.",
                    gettype($config['nextPageFlag']['stopOn']),
                ));
            }

            $this->field = $config['nextPageFlag']['field'];
            $this->stopOn = $config['nextPageFlag']['stopOn'];
            if (isset($config['nextPageFlag']['ifNotSet'])) {
                if (!is_bool($config['nextPageFlag']['ifNotSet'])) {
                    throw new UserException(sprintf(
                        "'ifNotSet' value must be boolean, given '%s' type.",
                        gettype($config['nextPageFlag']['ifNotSet']),
                    ));
                }
                $this->ifNotSet = $config['nextPageFlag']['ifNotSet'];
            } else {
                $this->ifNotSet = $this->stopOn;
            }
        }

        parent::__construct($scroller);
    }

    /**
     * @inheritdoc
     */
    public function getNextRequest(RestClient $client, JobConfig $jobConfig, $response, array $data): ?RestRequest
    {
        if ($this->hasMore($response) === false) {
            return null;
        }

        return $this->scroller->getNextRequest($client, $jobConfig, $response, $data);
    }

    /**
     * @param mixed $response
     */
    protected function hasMore($response): ?bool
    {
        if (empty($this->field)) {
            return null;
        }

        if (!isset($response->{$this->field})) {
            $value = $this->ifNotSet;
        } else {
            $value = $response->{$this->field};
        }

        if ((bool) $value === $this->stopOn) {
            return false;
        } else {
            return true;
        }
    }
}
