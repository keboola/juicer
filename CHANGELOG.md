TODO:
Downloader interface - SOAP + REST downloaders to use in a job, to replace json/soap Job
Decode USER json - a shortcut to throw user EX on json decode err?

1.1.1

- Fix: Configuration::getConfig() now properly returns an array with a single job instead of the job itself, if filtering by $rowId

1.1.0

**Run `php ./vendor/keboola/syrup/app/console extractor:generate-extractor` and overwrite services.yml and Job/Executor when updating to 1.1!**

- Bear in mind any changes to services.yml, eg. for Extractor class dependenc injection from parameters.yml
 ---

- Updated to Syrup 1.10
- "rowId" runtime parameter support
- Common\JobConfig replaces the configuration array for each job
	- allows execution of a single row from the config
- JobConfigs are supplied to Extractor in $config["jobs"] array
	- this array should be used for access to individual extractor jobs
	- JobConfig::getConfig() will return the same data as in $config array in <1.1.0
		- in other words, each item of $config["data"] is now accessed by $config["jobs"][$jobId]->getConfig()
	- ExtractorJob remains mostly unaffected, as the $config is set to the same contents as previously, only changing the __construct method
- Support for recursive calls:
	- [link](https://docs.google.com/a/keboola.com/spreadsheets/d/19IXHWVDfHOimDLDICUGLJ8guC7mxfPCZ_AgZ2Rl3HH4/edit#gid=0)
- Improved test coverage and documentation
- Parser::getDataFromPath() removed (use Keboola\Utils\Utils::getDataFromPath)
- Syrup Encryptor is now available in Extractor class
- ConfigsController uses Common\Configuration::getConfig() to retrieve a config detail
- PKs as an array (Implemented in Json\Parser)
	- Needs to be injected manually into the parser from Job
- Rewritten REST API backoff.
	- Jobs\RestJob no longer provides fallback
	- Use RestExtractor::getBackoff() (inherited by JsonExtractor)
	- Example: (GuzzleHttp\Client) `$client->getEmitter()->attach($this->getBackoff());`
- Fix Storage API error handling in ConfigsController
- Job::nextPage() now has a second parameter $data for better access to results for (eg) pagination purposes
	- Job::parse() should therefore return its dataset by default
- Extractor::saveLastJobTime();
	- A shortcut to save last job execution, success, failure etc..

1.0.7

- Fix Symfony version < 2.5.7 to prevent an error with double headers with Syrup 1.9

1.0.6

- Generator now properly highlights default choice for overwrite question

1.0.5

- Generator: overwrite routing.yml by default

1.0.4

- Properly check for --oauth parameter in Generator

1.0.3

- Fixed a typo in previous release

1.0.2

- Allow --config-columns argument for Generator command
	- eg: "endpoint,parameters,primaryKey"

1.0.1

- Generator command update: added command line options
	- --app-name [ex-appname]
	- --class-prefix [Appname]
	- --api-type [json|wsdl]
	- --parser [json|manual]
	- --oauth [1|2]

1.0.0

- Removed internal Common/Utils and Common/Table classes
	- Replaced Common\Utils with Keboola\Utils\Utils
	- Replaced Common\Table with Keboola\CsvTable\Table
- Use Syrup\ComponentBundle\Exception\UserException where Syrup\ComponentBundle\Exception\SyrupComponentException(400,...) was used
- Replaced (deprecated) Syrup\ComponentBundle\Filesystem\Temp with Keboola\Temp\Temp
- Updated README to reflect the entire process of creating a new application
- Added OAuth10Controller TODO & OAuth20Controller (including support & routing in Generator)
- Improved Exception handling in ConfigsController
- Improved type hinting and code documentation
- JsonExtractor now passes own Temp to Parser
- Extractor::sapiUpload() now looks into \Keboola\CsvTable\Table objects for Incremental and Table Name properties (in addition to Primary Key and Attributes)
- Job classes are now abstract (Job, Jobs\RestJob, Jobs\SoapJob, Jobs\JsonJob)

0.11.4

- Added /configs Controller & routing.yml to Generator
- Exp. fallback retries are now logged as DEBUG level (instead of WARN)

0.11.3

- Fixed OAuthController::getParam()

0.11.2

- Use SAPI Client created by Syrup in Executor instead of creating one
	- rewritten copying of mapping.json.twig from ex-bundle
	- removed deprecated createEsMapping from generate-extractor

0.11.1

- Use keboola/json-parser instead of own lib
- Fixed an error in RestJob::download() that could cause an error on using Retry-After header

0.11.0

- Syrup 1.9

0.10.3

- Syrup 1.9 init

0.10.2

- Inject the Syrup Job into Extractor object

0.10.1

- Fixed Generator's services.yml template - should now create properly working app again

0.10.0

- Updated to Syrup 1.8
	- Requires locks_db values added to parameters.yml

0.9.13

- Generator no tries to load some values from parameters.yml

0.9.12

- Generator command update

0.9.11

- Push _mapping to Elasticsearch server upon `composer install`
	- uses data from `Resources/elasticsearch/mapping.json`
	- uses server from parameters.yml - ["elasticsearch"]["hosts"][0]
- Use `psr-4` autoload method (instead of psr-0)

0.9.10

- Extractors\JsonExtractor struct loading fixed

0.9.9

- Dependency injection redesign
	- Extractor::__construct() is now unused
		- define it in conjunction with services.yml to access parameters.yml values etc
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

0.9.8

- Parser\Json::parse() will now try to analyze data if it encounters an unknown type

0.9.7

- Generator now asks before overwriting PHP files

0.9.6

- Finalized Generator

0.9.5

- bugfix

0.9.4

- Generator now creates Job\Executor

0.9.3

- fix release

0.9.2

- fix release

0.9.1

- Generator now creates ExtractorJob as well

0.9.0

- Async Syrup (component-bundle 1.7) update
- Log start&finish of each run
- Use Temp instead of TempService class from Syrup
	-	Syrup\ComponentBundle\Filesystem\TempService;
	+	Syrup\ComponentBundle\Filesystem\Temp;
- Extractor no longer extends Component
	- process() method is now public
	- run()'s $config parameter now contains an array of arrays with data and attributes:
		array(
			"attributes" => $attrs,	// config table attributes
			"data" => $rows			// config table data
		);
	- to access $params in run(), override the process() method
- Added extractor generator to create the Extractor's base classes
	- `php ./vendor/keboola/syrup/app/console extractor:generate-extractor`
	- may require registering the bundle: `php ./vendor/keboola/syrup/app/console syrup:register-bundle Keboola/ExtractorBundle`

0.8.12

- Prevent an error on ConfigsController::postAction() when a bucket doesn't exist

0.8.11

- Prevent an error in ConfigsController::getConfigAction() when the table is empty

0.8.10

- Fixed ConfigsController\getConfigAction($id)
	- Now properly returns table data & attributes

0.8.9

- Parser\JsonMap::parse() fix logging level

0.8.8

- Implemented more Controller\ConfigsController actions:
	- getConfigAction: returns all config attributes and data
	- getRowAction: returns a single row

0.8.7

- Allow an array of "column_name"=>"value" to be used as $parentId in Parser\Json::process()/parse()

0.8.6

- Extractor\Job now allows any kind of $client
- Parser\Json bugfixes
	- Should no longer generate a field for an empty object
	- Generated table&column name now tries to stay more readable
	- Save the full table name into a "fullDisplayName" attribute
- Fix Common\Table::addAttributes() failing if no attributes were set before

0.8.5

- Parser\Json fixes:
	-method process() now requires $data not to be empty if the structure is unknown
	-CSV header gets properly validated & length checked
	-properly handle arrays of arrays
	-ensure the generated SAPI table name is valid

0.8.4

- Add per-row operations to the ConfigsController

0.8.3

- Add "rowId"(PK) column to config tables in ConfigsController::postAction()

0.8.2

- ConfigsController POST call now tries to create bucket if it doesn't exist

0.8.1

- Fix a bug in Parser\Json::parse() where some fields in a JSON could have been skipped

0.8.0

- Major rewrite of Extractor\Extractor class to simplify the code
	- The whole flow is now controlled by run($config) method, where $config contains data sbout the configuration table from "config" runtime parameter
- Add Extractor\Extractors\JsonExtractor class that is responsible for creation of $parser object, using configuration bucket parameter json.struct as a cache
	- runtime parameter "analyze" (integer) can be used to define how many rows of data should be analyzed (default: 500). "analyze": "-1" can be used to always analyze all data
- Parser\Json::getAnalysed() renamed to hasAnalysed()
- Parser\Json::checkStruct() removed
- Parser\Json::process() now properly uses cached structure
- Minor bugfixes

0.7.6

- Added Parser\Json::process(array $data, $type = "root", $parentId = null)
	- uses Common\Cache to store data upon analysis (if needed)
	- when retrieving results using getCsvFiles(), the cache is processed before returning the result

0.7.5

- Added Common\Table class to expand CsvFile by attributes/primary keys.

0.7.4

- Set table attributes kbc.created_by and kbc.updated_by upon creating/writing into a table by an Extractor

0.7.3

- If a SAPI upload fails, an exception with code 400 is thrown (instead of 500 - to properly display the cause of an error)

0.7.2

- FIX: properly use Common\Logger in Job classes

0.7.1

- FIX: Job::updateRequest() fixed definition

0.7.0

- Job::init() now takes no parameters, using Job::$config.
- Job::init() is no longer called from __construct, being called in Extractor::initJob() instead
- $_log is gone from Registry and Extractor, using Common\Logger::setLogger() to init a logger and Common\Logger::log($level, $data, $context) to log
- Removed Registry class

0.6.1

- Removed temp from Registry, parser now uses its own TempService

0.6.0

- Upgrade to Guzzle 4.0
	- Extractor\RestJob::download() now expects a \GuzzleHttp\Message\Request created by GuzzleHttp\Client::createRequest()
	- Extractor\RestJob::download() updated to check response headers accordingly to the update
- Upgrade to Syrup 1.3
	- Renamed $_log, $_name, $_prefix, $_storageApi, _process() in Extractor to exclude the _

0.5.2

- Better exception handling in Extractor\RestJob::download()
- Drop query parameters with empty key

0.5.1

- Throw Syrup Exception with a HTTP Error code matching the error code returned in Extractor\RestJob::download()

0.5.0

- Extractor\Job::download() now only serves as a template for respective download functions in Extractor\Jobs\[Rest|Soap]Job::download()
- Extractor\Job::download() now only accepts one parameter $request
- Extractor\Job::__construct() second parameter $client is no longer optional
- Extractor\Jobs\JsonJob::analyze() replaces Extractor\Job::analyzeJson(), adds first parameter $type
- Parser\Parser::getDataFromPath() is now public and static

0.4.5

- Fix Parser\Json::analyseRow(): type change from NULL caused an error

0.4.4

- Utils::replaceDates() now properly handles multiple replacements in a single string

0.4.3

- Utils::replaceDates() now supports $format and $timezone params
- Extractor\Job::downloadRest() now logs bad request errors to SAPI before throwing an exception

0.4.2

- Extractor\Job::analyzeJson() now has a mandatory $path parameter (yes, I know it's an incompatible change, though used nearly nowhere!)

0.4.1

- Add bool Parser\Json::getAnalyzed() to see whether structure was updated

0.4.0

- NEW: JSON Parser:
	- Extractor\Job::analyzeJson($pages) downloads $pages amount of pages and analyses(Parser\Json\analyze()) the response, result is stored in Parser\Json::$struct
	- The analysed data should be cached and loaded to parse the JSON response (up to extractor to handle that)
	- Parser\Json::parse($data, $type) uses looks into $this->struct for the description of data to be parsed, and returns a list of JSON files as the result
	- Objects are "flattened" to be stored in "_" separated columns
	- Numbered arrays are stored in a separate file named by the parent.field, with an additional JSON_parentId column to link its data to the parent table
- NEW: Attr: sapi.ignoreTables
	- Comma separated list of result table names that should be skipped at upload to SAPI
- CHANGED: Extractor\Job::downloadRest() now returns object instead of associative array (basically running json_decode($data) instead of json_decode($data, true))
- CHANGED: Extractor\Job::downloadSoap() returns an object as well
- CHANGED: Parser\Parser::getDataFromPath() can now work with both objects and arrays
- CHANGED: Parser\Wsdl::parse() arguments order changed to make $path optional (now it goes $data, $type, $path)
- CHANGED: Extractor\Job::nextPage() definition no longer states the "array" type
