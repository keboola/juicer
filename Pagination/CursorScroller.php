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
class CursorScroller extends AbstractScroller implements ScrollerInterface
{
    /**
     * @var int
     */
    protected $max = null;
    /**
     * @var int
     */
    protected $min = null;
    /**
     * @var string
     */
    protected $idKey;
    /**
     * @var string
     */
    protected $param;
    /**
     * @var bool
     */
    protected $reverse = false;
    /**
     * @var int
     */
    protected $increment = 0;

    public function __construct(array $config)
    {
        parent::__construct($config);

        $this->idKey = $config['idKey'];
        $this->param = $config['param'];
        if (!empty($config['reverse'])) {
            $this->reverse = (bool) $config['reverse'];
        }
        if (!empty($config['increment'])) {
            $this->increment = $config['increment'];
        }
    }

    /**
     * @param array $config
     *     [
     *         'idKey' => string // mandatory parameter; key containing the "cursor"
     *         'param' => string // the cursor parameter
     *         'reverse' => bool // if true, the scroller looks for the lowest ID
     *         'increment' => int // add (or subtract using a negative number)
     *                               from the **numeric** cursor value
     *     ]
     * @return static
     */
    public static function create(array $config)
    {
        if (empty($config['idKey'])) {
            throw new UserException("Missing 'pagination.idKey' attribute required for cursor pagination");
        }

        if (empty($config['param'])) {
            throw new UserException("Missing 'pagination.param' attribute required for cursor pagination");
        }

        return new self($config);
    }

    /**
     * {@inheritdoc}
     */
    public function getFirstRequest(ClientInterface $client, JobConfig $jobConfig)
    {
        return $client->createRequest($jobConfig->getConfig());
    }

    /**
     * {@inheritdoc}
     */
    public function getNextRequest(ClientInterface $client, JobConfig $jobConfig, $response, $data)
    {
        if (empty($data) || false === $this->hasMore($response)) {
            $this->reset();
            return false;
        } else {
            $cursor = 0;

            foreach($data as $item) {
                $cursorVal = Utils::getDataFromPath($this->idKey, $item, '.');

                if (is_null($this->max) || $cursorVal > $this->max) {
                    $this->max = $cursorVal;
                }

                if (is_null($this->min) || $cursorVal < $this->min) {
                    $this->min = $cursorVal;
                }

                $cursor = $this->reverse ? $this->min : $this->max;
            }

            if (0 !== $this->increment) {
                if (!is_numeric($cursor)) {
                    throw new UserException("Trying to increment a pointer that is not numeric.");
                }

                $cursor += $this->increment;
            }

            $jobConfig->setParam($this->param, $cursor);

            return $client->createRequest($jobConfig->getConfig());
        }
    }

    public function reset()
    {
        $this->max = $this->min = null;
    }
}
