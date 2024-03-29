<?php

declare(strict_types=1);

namespace Keboola\Juicer\Pagination\Decorator;

use Keboola\Juicer\Client\RestClient;
use Keboola\Juicer\Client\RestRequest;
use Keboola\Juicer\Config\JobConfig;
use Keboola\Juicer\Exception\UserException;
use Keboola\Juicer\Pagination\ScrollerInterface;
use Psr\Log\LoggerInterface;
use function Keboola\Utils\getDataFromPath;

/**
 * Class LimitStopScrollerDecorator
 * Adds 'limit' option
 */
class LimitStopScrollerDecorator extends AbstractScrollerDecorator
{
    private ?int $countLimit = null;

    private ?string $fieldName = null;

    private int $currentCount;

    public function __construct(ScrollerInterface $scroller, array $config, LoggerInterface $logger)
    {
        parent::__construct($scroller, $logger);
        if (!empty($config['limitStop'])) {
            if (empty($config['limitStop']['field']) && empty($config['limitStop']['count'])) {
                throw new UserException("One of 'limitStop.field' or 'limitStop.count' attributes is required.");
            }
            if (!empty($config['limitStop']['field']) && !empty($config['limitStop']['count'])) {
                throw new UserException("Specify only one of 'limitStop.field' or 'limitStop.count'.");
            }
            if (!empty($config['limitStop']['field'])) {
                $this->fieldName = $config['limitStop']['field'];
            }
            if (!empty($config['limitStop']['count'])) {
                $this->countLimit = intval($config['limitStop']['count']);
            }
        }
        $this->reset();
    }

    /**
     * @inheritdoc
     */
    public function getFirstRequest(RestClient $client, JobConfig $jobConfig): ?RestRequest
    {
        $this->currentCount = 0;
        return $this->scroller->getFirstRequest($client, $jobConfig);
    }

    /**
     * @inheritdoc
     */
    public function getNextRequest(RestClient $client, JobConfig $jobConfig, $response, array $data): ?RestRequest
    {
        $this->currentCount += count($data);
        if ($this->fieldName) {
            $limit = getDataFromPath($this->fieldName, $response, '.');
        } else {
            $limit = $this->countLimit;
        }
        if ($this->currentCount >= $limit) {
            $this->logger->info(sprintf(
                'Limit reached, stopping scrolling. Current count: %d, limit: %d',
                $this->currentCount,
                $limit,
            ));
            return null;
        }

        return $this->scroller->getNextRequest($client, $jobConfig, $response, $data);
    }

    /**
     * @inheritdoc
     */
    public function reset(): void
    {
        $this->currentCount = 0;
        parent::reset();
    }
}
