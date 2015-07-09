This readme assumes knowledge of [Syrup environment](https://sites.google.com/a/keboola.com/devel/kbc/syrup/quickstart). Please refer to Syrup's documentation for basic informations!

## 0. Environment init

### Connect to elasticsearch
Ensure you can connect to Elasticsearch and the databases specified in parameters_shared.yml!

Skip if yer developing on Devel!

KBC Devel:
`ssh kbc-devel-02.keboola.com -L 9200:VPC-ELB-ElasticSearch-Syrup-Test-651343387.us-east-1.elb.amazonaws.com:9200 -L 3306:localhost:3306`

## 1. Create the extractor
`$ git clone git@github.com:keboola/extractor-generator.git`

`$ php extractor-generator/generate.php`

Follow interactive interface

Remove generator

`$ rm -rf extractor-generator`

## 2. Prepare the Elasticsearch Index
`php ./vendor/keboola/syrup/app/console syrup:create-index`

## 3. Get to work!
- Edit YourAppExtractor.php and YourAppExtractorJob.php
- Edit Resources/config/services.yml if you need to use **parameters.yml** values in the application:
	- Example:
		- services.yml:

				ex_twitter.extractor:
					class: Keboola\TwitterExtractorBundle\TwitterExtractor
					arguments: ['%twitter%']

		- TwitterExtractor.php

				/** @var array */
				protected $apiKeys;

				public function __construct($twitter) {
					$this->apiKeys = $twitter;
				}

		- parameters.yml

				twitter:
					api-key: WoWSuchApiKey16777216489
					api-secret: OMGICantBelieveH0wS3cr3tIAmTh4t5cr42yTr00l0l0lOhai

I found it easier for development to print out messages to stdout when using the run-job command. That can be done by editing the **monolog** parameter in `vendor/keboola/syrup/app/config/config_dev.yml`:

	monolog:
		handlers:
			syslog:
				type:                stream
				path:                php://stdout
				level:               debug
				bubble:              false

For more information about Syrup bundles check out the [documentation](https://sites.google.com/a/keboola.com/devel/kbc/syrup/quickstart)
