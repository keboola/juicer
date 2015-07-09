<?php

namespace Keboola\ExtractorBundle\Tests\Extractor;

use	Keboola\ExtractorBundle\Extractor\Extractor,
	Keboola\ExtractorBundle\Config\Config;

/**
 * Because abstract is right
 */
class MockExtractor extends Extractor
{
	public function run(Config $config)
	{
		// SUCH STUFF WOW
	}
}
