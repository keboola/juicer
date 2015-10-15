<?php

namespace Keboola\Juicer\Pagination;

use	Keboola\Juicer\Client\ClientInterface,
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

	public function __construct($urlParam = 'next_page', $includeParams = false)
	{
		$this->urlParam = $urlParam;
		$this->includeParams = $includeParams;
	}

	public static function create(array $config)
	{
		return new self(
			!empty($config['urlKey']) ? $config['urlKey'] : 'next_page',
			!empty($config['includeParams']) ? (bool) $config['includeParams'] : false
		);
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
