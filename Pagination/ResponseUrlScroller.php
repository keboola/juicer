<?php

namespace Keboola\Juicer\Pagination;

use Keboola\Juicer\Client\ClientInterface,
    Keboola\Juicer\Config\JobConfig;

/**
 * Scrolls using URL or Endpoint within page's response.
 *
 *
 */
class ResponseUrlScroller extends AbstractResponseScroller implements ScrollerInterface
{
    /**
     * @var string
     */
    protected $urlParam;

    /**
     * @var bool
     */
    protected $includeParams;

    public function __construct($config)
    {
        $this->urlParam = !empty($config['urlKey']) ? $config['urlKey'] : 'next_page';
        $this->includeParams = !empty($config['includeParams']) ? (bool) $config['includeParams'] : false;

        parent::__construct($config);
    }

    public static function create(array $config)
    {
        return new self($config);
    }

    /**
     * {@inheritdoc}
     */
    public function getNextRequest(ClientInterface $client, JobConfig $jobConfig, $response, $data)
    {
        if (empty($response->{$this->urlParam})) {
            return false;
        } else {
            $config = $jobConfig->getConfig();
            $config['endpoint'] = $response->{$this->urlParam};
            if (!$this->includeParams) {
                $config['params'] = [];
            }

            return $client->createRequest($config);
        }
    }
}
