<?php

declare(strict_types=1);

namespace Keboola\Juicer\Pagination;

use Keboola\Juicer\Client\RestClient;
use Keboola\Juicer\Exception\UserException;
use Keboola\Juicer\Config\JobConfig;
use function Keboola\Utils\getDataFromPath;

/**
 * Looks within the response **data** for an ID
 * which is then used as a parameter for scrolling
 */
class CursorScroller extends AbstractScroller implements ScrollerInterface
{
    protected ?int $max = null;

    protected ?int $min = null;

    protected string $idKey;

    protected string $param;

    protected bool $reverse = false;

    protected int $increment = 0;

    /**
     * FacebookResponseUrlScroller constructor.
     * @param array $config
     *      [
     *          'idKey' => string // mandatory parameter; key containing the "cursor"
     *          'param' => string // the cursor parameter
     *          'reverse' => bool // if true, the scroller looks for the lowest ID
     *          'increment'  => int // add (or subtract using a negative number) from the **numeric** cursor value
     *      ]
     * @throws UserException
     */
    public function __construct(array $config)
    {
        if (empty($config['idKey'])) {
            throw new UserException("Missing 'pagination.idKey' attribute required for cursor pagination");
        }
        if (empty($config['param'])) {
            throw new UserException("Missing 'pagination.param' attribute required for cursor pagination");
        }

        $this->idKey = $config['idKey'];
        $this->param = $config['param'];
        if (isset($config['reverse'])) {
            $this->reverse = (bool) $config['reverse'];
        }
        if (!empty($config['increment'])) {
            $this->increment = $config['increment'];
        }
    }

    /**
     * @inheritdoc
     */
    public function getFirstRequest(RestClient $client, JobConfig $jobConfig)
    {
        return $client->createRequest($jobConfig->getConfig());
    }

    /**
     * @inheritdoc
     */
    public function getNextRequest(RestClient $client, JobConfig $jobConfig, $response, array $data)
    {
        if (empty($data)) {
            $this->reset();
            return false;
        } else {
            $cursor = 0;

            foreach ($data as $item) {
                $cursorVal = getDataFromPath($this->idKey, $item, '.');
                if (!is_numeric($cursorVal)) {
                    throw new UserException(sprintf(
                        "Cursor value '%s' is not numeric.",
                        json_encode($cursorVal)
                    ));
                }
                $cursorVal = (int) $cursorVal;

                if (is_null($this->max) || $cursorVal > $this->max) {
                    $this->max = $cursorVal;
                }

                if (is_null($this->min) || $cursorVal < $this->min) {
                    $this->min = $cursorVal;
                }

                $cursor = $this->reverse ? $this->min : $this->max;
            }

            if ($this->increment !== 0) {
                $cursor += $this->increment;
            }

            $jobConfig->setParam($this->param, $cursor);

            return $client->createRequest($jobConfig->getConfig());
        }
    }

    /**
     * @inheritdoc
     */
    public function reset(): void
    {
        $this->max = $this->min = null;
    }
}
