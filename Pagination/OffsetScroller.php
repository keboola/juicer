<?php

namespace Keboola\Juicer\Pagination;

use	Keboola\Juicer\Exception\UserException,
	Keboola\Juicer\Client\ClientInterface,
	Keboola\Juicer\Config\JobConfig;

/**
 * Scrolls using simple "limit" and "offset" query parameters.
 * Limit can be overriden in job's config's query parameters
 * and it will be used instead of extractor's default
 * @todo This should probably set the $limit in the first page request as well
 * 	- pass first request through scroller too? (getFirstRequest(Request $request) or such?)
 */
class OffsetScroller implements ScrollerInterface
{
	/**
	 * @var int
	 */
	protected $limit;
	/**
	 * @var string
	 */
	protected $limitParam;
	/**
	 * @var string
	 */
	protected $offsetParam;
	/**
	 * @var int
	 */
	protected $pointer = 0;

	public function __construct($limit, $limitParam = 'limit', $offsetParam = 'offset')
	{
		$this->limit = $limit;
		$this->limitParam = $limitParam;
		$this->offsetParam = $offsetParam;
	}

	public static function create(array $config)
	{
		if (empty($config['limit'])) {
			throw new UserException("Missing 'pagination.limit' attribute required for offset pagination");
		}

		return new self(
			$config['limit'],
			!empty($config['limitParam']) ? $config['limitParam'] : 'limit',
			!empty($config['offsetParam']) ? $config['offsetParam'] : 'offset'
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function getNextRequest(ClientInterface $client, JobConfig $jobConfig, $response, $data)
	{
		$params = $jobConfig->getParams();
		$limit = empty($params[$this->limitParam]) ? $this->limit : $params[$this->limitParam];

		if (count($data) < $limit) {
			$this->reset();
			return false;
		} else {
			$this->pointer += $limit;

			$config = $jobConfig->getConfig();
			$config['params'] = array_replace(
				$jobConfig->getParams(),
				[
					$this->limitParam => $limit,
					$this->offsetParam => $this->pointer
				]
			);

			return $client->createRequest($config);
		}
	}

	public function reset()
	{
		$this->pointer = 0;
	}
}
