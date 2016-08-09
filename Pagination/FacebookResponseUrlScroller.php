<?php
/**
 * @author Erik Zigo <erik.zigo@keboola.com>
 */
namespace Keboola\Juicer\Pagination;

use GuzzleHttp\Url;
use Keboola\Juicer\Client\ClientInterface,
	Keboola\Juicer\Config\JobConfig;
use Keboola\Utils\Utils;
use GuzzleHttp\Query;

class FacebookResponseUrlScroller extends AbstractResponseScroller implements ScrollerInterface
{
	/**
	 * @var string
	 */
	protected $urlParam;

	/**
	 * @var bool
	 */
	protected $includeParams;

	/**
	 * @var bool
	 */
	protected $paramIsQuery = false;

	public function __construct($config)
	{
		$this->urlParam = !empty($config['urlKey']) ? $config['urlKey'] : 'paging.next';
		$this->includeParams = !empty($config['includeParams']) ? (bool) $config['includeParams'] : false;
		$this->paramIsQuery = !empty($config['paramIsQuery']) ? (bool) $config['paramIsQuery'] : false;
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
		$nextUrl = Utils::getDataFromPath($this->urlParam, $response, '.');

		if (empty($nextUrl)) {
			return false;
		}

		// since validation - cannot be greater than now
		$now = new \DateTime();
		$sinceDateTime = \DateTime::createFromFormat('U', Url::fromString($nextUrl)->getQuery()->get('since'));

		if ($sinceDateTime && $sinceDateTime > $now) {
			return false;
		}

		$config = $jobConfig->getConfig();

		if (!$this->includeParams) {
			$config['params'] = [];
		}

		if (!$this->paramIsQuery) {
			$config['endpoint'] = $nextUrl;
		} else {
			// Create an array from the query string
			$responseQuery = Query::fromString(ltrim($nextUrl, '?'))->toArray();
			$config['params'] = array_replace($config['params'], $responseQuery);
		}

		return $client->createRequest($config);
	}
}
