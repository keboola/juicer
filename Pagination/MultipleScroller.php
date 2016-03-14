<?php

namespace Keboola\Juicer\Pagination;

use Keboola\Juicer\Exception\UserException,
    Keboola\Juicer\Client\ClientInterface,
    Keboola\Juicer\Config\JobConfig;
use Keboola\Utils\Utils;

/**
 * Looks within the response **data** for an ID
 * which is then used as a parameter for scrolling
 */
class MultipleScroller extends AbstractScroller implements ScrollerInterface
{
    /**
     * @var ScrollerInterface[]
     */
    protected $scrollers = [];

    /**
     * @var string
     */
    protected $defaultScroller;

    public function __construct(array $config)
    {
        parent::__construct($config);

        if (empty($config['scrollers'])) {
            throw new UserException('At least one scroller must be configured for "multiple" scroller.');
        }

        foreach($config['scrollers'] as $id => $scrollerCfg) {
            $this->scrollers[$id] = ScrollerFactory::getScroller($scrollerCfg);
        }

        if (!empty($config['default'])) {
            $this->defaultScroller = $config['default'];
        }
    }

    /**
     * @param array $config
     *     [
     *
     *     ]
     * @return static
     */
    public static function create(array $config)
    {
        return new self($config);
    }

    /**
     * {@inheritdoc}
     */
    public function getFirstRequest(ClientInterface $client, JobConfig $jobConfig)
    {
        return $this->getScrollerForJob($jobConfig)->getFirstRequest($client, $jobConfig);
    }

    /**
     * {@inheritdoc}
     */
    public function getNextRequest(ClientInterface $client, JobConfig $jobConfig, $response, $data)
    {
        return $this->getScrollerForJob($jobConfig)->getNextRequest($client, $jobConfig, $response, $data);
    }

    public function reset()
    {
        foreach($this->scrollers as $scroller) {
            $scroller->reset();
        }
    }

    public function getScrollers()
    {
        return $this->scrollers;
    }

    /**
     * @param JobConfig $jobConfig
     * @return ScrollerInterface
     */
    public function getScrollerForJob(JobConfig $jobConfig)
    {
        if (empty($jobConfig->getConfig()['scroller'])) {
            if (empty($this->defaultScroller)) {
                return new NoScroller();
            }

            if (!array_key_exists($this->defaultScroller, $this->scrollers)) {
                throw new UserException("Default scroller '{$this->defaultScroller}' does not exist");
            }

            return $this->scrollers[$this->defaultScroller];
        }

        $scrollerId = $jobConfig->getConfig()['scroller'];

        if (empty($this->scrollers[$scrollerId])) {
            throw new UserException(
                "Scroller '{$scrollerId}' not set in API definitions. Scrollers defined: "
                . join(', ', array_keys($this->scrollers))
            );
        }

        return $this->scrollers[$scrollerId];
    }
}
