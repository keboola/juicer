<?php
use	Keboola\ExtractorBundle\Config\Configuration,
	Keboola\ExtractorBundle\Config\Config;
use Keboola\Temp\Temp;

class ConfigurationTest extends ExtractorTestCase
{
	/**
	 * @var Configuration
	 */
	protected $configuration;

	public function testGetConfig()
	{
		// TODO create and fill the table first, then compare actual contents!
		$config = $this->configuration->getConfig(['config' => "test"], "sys.c-extractor-bundle-test");
		$this->assertContainsOnlyInstancesOf("\Keboola\ExtractorBundle\Config\JobConfig", $config->getJobs());
		$this->assertEquals(["apiKey" => "value"], $config->getAttributes());
	}

	public function testGetConfigByRowId()
	{
		$config = $this->configuration->getConfig(['config' => "test", 'rowId' => 2], "sys.c-extractor-bundle-test");
		$this->assertContainsOnlyInstancesOf("\Keboola\ExtractorBundle\Config\JobConfig", $config->getJobs());
		$this->assertCount(1, $config->getJobs());
		$this->assertEquals(2, $config->getJobs()[2]->getJobId());
	}

		public function testCheckConfig()
	{
		$config = new Config("ex-test-aaa", "cnfname", []);
// 		[
// 			'attributes' => [
// 				'sapi' => [
// 					'incremental' => true
// 				],
// 				'apiKey' => 'someKey'
// 			]
// 		];
		$config->setAttributes(['apiKey' => 'someKey']);

		self::callMethod($this->configuration, 'checkConfig', [$config]);

// 		$this->assertEquals('test-aaa', PHPUnit_Framework_Assert::readAttribute($this->extractor, 'name'));
	}

	/**
	 * @expectedException \Keboola\ExtractorBundle\Exception\UserException
	 */
	public function testCheckConfigFail()
	{
		self::callMethod($this->configuration, 'checkConfig', [
			new Config("ex-test-aaa", "cnfname", [])
		]);
	}

	public function setUp()
	{
		parent::setUp();
		$this->configuration = new Configuration("ex-test", new Temp("extractorTest"), ['apiKey']);
		$this->configuration->setStorageApi($this->sapiClient);
	}
}
