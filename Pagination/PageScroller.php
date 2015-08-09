<?php

namespace Keboola\Juicer\Pagination;

use	Keboola\Juicer\Client\ClientInterface,
	Keboola\Juicer\Config\JobConfig;

/**
 * Scrolls using simple "limit" and "page" query parameters.
 *
 * Limit can be overriden in job's config's query parameters
 * and it will be used instead of extractor's default.
 * Pagination will stop if an empty response is received,
 * or when $limit is set and
 */
class PageScroller implements ScrollerInterface
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
	protected $pageParam;
	/**
	 * @var int
	 */
	protected $page;
	/**
	 * @var int
	 */
	protected $firstPage;


	public function __construct($pageParam = 'page', $limit = null, $limitParam = 'limit', $firstPage = 1)
	{
		$this->pageParam = $pageParam;
		$this->limit = $limit;
		$this->limitParam = $limitParam;
		$this->firstPage = $this->page = $firstPage;
	}

	public static function create(array $config)
	{
		return new self(
			!empty($config['pageParam']) ? $config['pageParam'] : 'page',
			!empty($config['limit']) ? $config['limit'] : null,
			!empty($config['limitParam']) ? $config['limitParam'] : 'limit',
			!empty($config['firstPage']) ? $config['firstPage'] : 1
		);
	}

	public function getNextRequest(ClientInterface $client, JobConfig $jobConfig, $response, $data)
	{
		$params = $jobConfig->getParams();
		$limit = empty($params[$this->limitParam]) ? $this->limit : $params[$this->limitParam];

		if ((is_null($limit) && empty($data)) || (count($data) < $limit)) {
			$this->reset();
			return false;
		} else {
			$this->page++;

			if (!empty($this->limitParam) && !is_null($limit)) {
				$params[$this->limitParam] = $limit;
			}

			$config = $jobConfig->getConfig();
			$config['params'] = array_replace(
				$jobConfig->getParams(),
				[$this->pageParam => $this->page]
			);

			return $client->createRequest($config);
		}
	}

	public function reset()
	{
		$this->page = $this->firstPage;
	}
}
