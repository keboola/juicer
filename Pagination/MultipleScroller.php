<?php

namespace Keboola\Juicer\Pagination;

use Keboola\Juicer\Client\RestClient;
use Keboola\Juicer\Exception\UserException;
use Keboola\Juicer\Config\JobConfig;

/**
 * Looks within the response **data** for an ID
 * which is then used as a parameter for scrolling
 */
class MultipleScroller extends AbstractScroller implements ScrollerInterface
{
    /**
     * @var ScrollerInterface[]
     */
    private array $scrollers = [];

    private string $defaultScroller;

    /**
     * MultipleScroller constructor.
     * @param array $config
     *      [
     *          'scrollers' => array // named definitions of scrollers
     *          'default' => string // name of default scroller
     *      ]
     * @throws UserException
     */
    public function __construct(array $config)
    {
        if (empty($config['scrollers'])) {
            throw new UserException('At least one scroller must be configured for "multiple" scroller.');
        }

        foreach ($config['scrollers'] as $id => $scrollerCfg) {
            if (!is_array($scrollerCfg)) {
                throw new UserException('Scroller configuration for ' . $id . 'must be array.');
            }
            $this->scrollers[$id] = ScrollerFactory::getScroller($scrollerCfg);
        }

        if (!empty($config['default'])) {
            $this->defaultScroller = $config['default'];
        }
    }

    public function __clone()
    {
        foreach ($this->scrollers as $index => $scroller) {
            $this->scrollers[$index] = clone $scroller;
        }
    }

    /**
     * @inheritdoc
     */
    public function getFirstRequest(RestClient $client, JobConfig $jobConfig)
    {
        return $this->getScrollerForJob($jobConfig)->getFirstRequest($client, $jobConfig);
    }

    /**
     * @inheritdoc
     */
    public function getNextRequest(RestClient $client, JobConfig $jobConfig, $response, $data)
    {
        return $this->getScrollerForJob($jobConfig)->getNextRequest($client, $jobConfig, $response, $data);
    }

    /**
     * @inheritdoc
     */
    public function reset()
    {
        foreach ($this->scrollers as $scroller) {
            $scroller->reset();
        }
    }

    /**
     * Get configured scrollers
     * @return ScrollerInterface[]
     */
    public function getScrollers(): array
    {
        return $this->scrollers;
    }

    /**
     * @throws UserException
     */
    private function getScrollerForJob(JobConfig $jobConfig): ScrollerInterface
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
