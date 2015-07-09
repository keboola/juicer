<?php
/**
 * Created by Ondrej Vana <kachna@keboola.com>
 * Date: 17/09/14
 */

namespace Keboola\ExtractorBundle\Command;


use	Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use	Symfony\Component\Console\Input\InputInterface,
	Symfony\Component\Console\Input\InputOption,
	Symfony\Component\Console\Output\OutputInterface;
use	Syrup\ComponentBundle\Job\Metadata\JobManager;
use	GuzzleHttp\Client as Guzzle;
use	Symfony\Component\Yaml\Yaml;
use	Keboola\Utils\Utils;

/**
 * @todo
 * create getApiName() to alias the explode("-", $appName, 2)[1];
 */

class GenerateExtractorCommand extends ContainerAwareCommand
{
	/** @var \Twig_Environment */
	protected $twig;

	/** @var bool */
	protected $isInteractive;

	protected function configure()
	{
		$this
			->setName('extractor:generate-extractor')
			->setDescription('Create new Extractor Bundle')
			->addOption('app-name', null, InputOption::VALUE_OPTIONAL, "Application short name, ie. '<comment>ex-appname</comment>'")
			->addOption('class-prefix', null, InputOption::VALUE_OPTIONAL, "Application class prefix, ie. '<comment>Appname</comment>'")
			->addOption('api-type', null, InputOption::VALUE_OPTIONAL, "Application type [<comment>json</comment> or <comment>wsdl</comment>")
			->addOption('parser', null, InputOption::VALUE_OPTIONAL, "JSON Parser type [<comment>json</comment> or <comment>manual</comment>")
			->addOption('config-columns', null, InputOption::VALUE_OPTIONAL, "Configuration table columns (comma separated list)")
			->addOption('oauth', null, InputOption::VALUE_OPTIONAL, "OAuth type [<comment>1</comment> or <comment>2</comment>]")
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$dialog = $this->getHelper('dialog');

		$this->isInteractive = $input->isInteractive();

		if ($input->getOption('app-name')) {
			$appName = $input->getOption('app-name');
			$output->writeln("<info>Using application short name '<options=bold>{$appName}</options=bold>'</info>");
		} else {
			$this->checkInteractive();
			$defaultAppName = $this->getContainer()->hasParameter("app_name")
				? $this->getContainer()->getParameter("app_name")
				: "ex-appname";

			$appName = $dialog->ask(
				$output,
				"Please enter the <question>component name</question> (should follow '<info>ex-appname</info>')[<options=bold>{$defaultAppName}</options=bold>]: ",
				$defaultAppName
			);
		}

		if ($input->getOption('class-prefix')) {
			$className = $input->getOption('class-prefix');
			$output->writeln("<info>Using application Class prefix '<options=bold>{$className}</options=bold>'</info>");
		} else {
			$this->checkInteractive();
			$defaultClassName = Utils::camelize(substr($appName, 3), true);
			$className = $dialog->ask(
				$output,
				"Bundle/Class name prefix (eg. {$defaultClassName} for Keboola/{$defaultClassName}ExtractorBundle/{$defaultClassName}Extractor) [<options=bold>{$defaultClassName}</options=bold>]: ",
				$defaultClassName
			);
		}

		$loader = new \Twig_Loader_Filesystem('vendor/keboola/extractor-bundle/Resources/templates');
		$this->twig = new \Twig_Environment($loader, array(
		//     'cache' => '/path/to/compilation_cache',
		));

		$this->createSkeleton($appName, $className, $input, $output, $dialog);
		$this->createServices($appName, $className, $output, $dialog);
		$this->createConfigs($appName, $className, $output, $dialog, $input->getOption('config-columns'));
		if (in_array($input->getOption('oauth'), [1,2]) || $dialog->askConfirmation(
			$output,
			"Create <question>OAuth</question> controller and routing? [y/<options=bold>n</options=bold>]: ",
			false
		)) {
			$this->createOAuth($appName, $className, $output, $dialog, $input->getOption('oauth'));
		}
	}

	protected function createServices($appName, $className, $output, $dialog)
	{
		$yamlPath = "Resources/config/services.yml";
		$output->writeln("<info>Creating <comment>{$yamlPath}</comment></info>");

		/** @var Yaml $yaml */
		$yaml = new Yaml();

		if (!file_exists($yamlPath) || !is_array($yamlData = $yaml->parse(file_get_contents($yamlPath)))) {
			$yamlData = array();
		}

		$template = $this->twig->loadTemplate('Resources/config/services.yml.twig');
		$svcTemplate = $template->render(array(
			"classPrefix" => $className,
			"appName" => str_replace("-", "_", $appName)
		));

		$templateData = $yaml->parse($svcTemplate);

		$result = array_replace_recursive($yamlData, $templateData);

		if (!is_dir('Resources/config')) {
			mkdir('Resources/config', 0755, true);
		}

		file_put_contents($yamlPath, $yaml->dump($result, 3));

		$output->writeln("<info>YAML file saved</info>");
	}

	protected function createConfigs($appName, $className, $output, $dialog, $confTableCols = null)
	{
		if ($this->checkFileOverwrite($dialog, $output, "Controller/ConfigsController.php")) {
			$confTableCols = empty($confTableCols) && $this->checkInteractive()
				? $dialog->ask(
					$output,
					"<info>Enter column names for the configuration table (comma separated, <comment>rowId</comment> is appended by default): </info>",
					"endpoint, params"
				)
				: $confTableCols;
			$columns = preg_split('/\s*,\s*/', trim($confTableCols));

			$confCtrl = $this->twig->loadTemplate('Controller/ConfigsController.php.twig');
			file_put_contents("Controller/ConfigsController.php", $confCtrl->render(array(
				"classPrefix" => $className,
				"appName" => $appName,
				"tableColumns" => var_export($columns, true)
			)));
			$output->writeln("<info>Controller/ConfigsController.php created</info>");
		}

		$routingYmlPathname = "Resources/config/routing.yml";
		if ($this->checkFileOverwrite($dialog, $output, $routingYmlPathname, true)) {
			$template = $this->twig->loadTemplate("{$routingYmlPathname}.twig");
			$routingTemplate = $template->render(array(
				"classPrefix" => $className,
				"appName" => explode("-", $appName, 2)[1]
			));

			if (!file_exists($routingYmlPathname)) {
				file_put_contents($routingYmlPathname, $routingTemplate);
				$output->writeln("<info>{$routingYmlPathname} saved</info>");
			} elseif ($dialog->askConfirmation(
				$output,
				"Add 'config API' routing data to existing {$routingYmlPathname}? [<options=bold>y</options=bold>/n]: ",
				true
			)) {
				$yaml = new Yaml();
				$templateData = $yaml->parse($routingTemplate);

				$result = array_replace_recursive($yaml->parse(file_get_contents($routingYmlPathname)), $templateData);
				file_put_contents($routingYmlPathname, $yaml->dump($result, 3));

				$output->writeln("<info>{$routingYmlPathname} saved</info>");
			} else {
				$output->writeln("<error>{$routingYmlPathname} generation skipped</error>");
			}
		}
	}

	protected function createSkeleton($appName, $className, $input, $output, $dialog)
	{
		$output->writeln("Define the Extractor type:");

		$api = $input->getOption('api-type');
		if (in_array($api, ['json', 'wsdl'])) {
			$apiType = ($api == "json") ? 0 : 1;
			$output->writeln("<info>Using api type '<options=bold>{$api}</options=bold>'</info>");
		} else {
			$this->checkInteractive();
			$apiType = $dialog->select(
				$output,
				"<question>API type</question>",
				["JSON through REST (Using GuzzleHttp)", "WSDL (Using PHP's \\SoapClient)"]
			);
		}

		if ($apiType == 1) { // WSDL
			$parser = "Wsdl";
			$apiClient = "SoapClient";
			$parentJob = "SoapJob";
			// TODO add client and parser to Extractor class
		} else { // JSON
			$p = $input->getOption('parser');
			if (in_array($p, ['json', 'manual'])) {
				$parserNo = ($p == "json") ? 0 : 1;
			} else {
				$this->checkInteractive();
				$parserNo = $dialog->select(
					$output,
					"<question>Parser type</question>",
					["*JSON parser", "JSON Manual mapping"],
					0
				);
			}

			$parser = ($parserNo == 0) ? "Json" : "JsonMap";
			$output->writeln("<info>Using '<options=bold>{$parser}</options=bold>' parser</info>");
			$apiClient = "GuzzleHttp\Client";
			$parentJob = "JsonJob";
		}

		$parentExtractor = ($parser == "Json")
			? "Extractors\\JsonExtractor"
			: (($apiType == 0) // Rest
				? "Extractors\\RestExtractor"
				: "Extractor");
		$app = explode("-", $appName, 2);

		if ($this->checkFileOverwrite($dialog, $output, "{$className}Extractor.php")) {
			$extractor = $this->twig->loadTemplate('Extractor.php.twig');
			file_put_contents("{$className}Extractor.php", $extractor->render(array(
				"classPrefix" => $className,
				"appName" => $app[1],
				"parentExtractor" => $parentExtractor,
				"clientClass" => $apiClient
			)));
			$output->writeln("<info>{$className}Extractor.php created</info>");
		}

		if ($this->checkFileOverwrite($dialog, $output, "{$className}ExtractorJob.php")) {
			$job = $this->twig->loadTemplate('ExtractorJob.php.twig');
			file_put_contents("{$className}ExtractorJob.php", $job->render(array(
				"classPrefix" => $className,
				"parentJob" => $parentJob
			)));
			$output->writeln("<info>{$className}ExtractorJob.php created</info>");
		}

		if ($this->checkFileOverwrite($dialog, $output, "Job/Executor.php")) {
			$executor = $this->twig->loadTemplate('Job/Executor.php.twig');
			if (!is_dir('Job')) {
				mkdir('Job', 0755);
			}
			file_put_contents("Job/Executor.php", $executor->render(array(
				"classPrefix" => $className,
				"appName" => $appName
			)));
			$output->writeln("<info>Job/Executor.php created</info>");
		}

		// Copy the default ES mapping parameters twig
		$esTwigPath = "Resources/views/Elasticsearch/mapping.json.twig";
		$output->writeln("<info>Creating mapping TWIG at <comment>{$esTwigPath}</comment></info>");
		if ($this->checkFileOverwrite($dialog, $output, $esTwigPath)) {
			$dir = dirname($esTwigPath);
			if (!is_dir($dir)) {
				mkdir($dir, 0755, true);
			}
			copy('vendor/keboola/extractor-bundle/Resources/templates/' . $esTwigPath, $esTwigPath);
		}
	}

	protected function createOAuth($appName, $className, $output, $dialog, $version = null)
	{
		$OAuthVersion = is_null($version) && $this->checkInteractive()
			? $dialog->select(
				$output,
				"<question>OAuth version</question>",
				['1' => "1.0", "2" => "2.0"]
			)
			: $version;

		switch ($OAuthVersion) {
			case 1: // 1.0
				$template = $this->twig->loadTemplate('Controller/OAuth10Controller.php.twig');
				break;
			case 2: // 2.0
				$template = $this->twig->loadTemplate('Controller/OAuth20Controller.php.twig');
				break;
			default:
				throw new \Exception("Unknown OAuth version selected!");
				break;
		}

		$ctrlPath = "Controller/OAuthController.php";
		if ($this->checkFileOverwrite($dialog, $output, $ctrlPath)) {
			file_put_contents($ctrlPath, $template->render(array(
				"classPrefix" => $className,
				"appName" => $appName
			)));
			$output->writeln("<info>{$ctrlPath} created</info>");
		}

		$apiName = explode("-", $appName, 2)[1];
		if ($OAuthVersion == 1) { // OAuth 1
			$apiKeys =
"        api-key: yourApiKey
        api-secret: yourApiSecret";
		} else {
			$apiKeys =
"        client-id: yourClientId
        client-secret: yourClientSecret";
		}

		$output->write("<info>Add the following to your parameters.yml and set according to values you've received from the API provider:
    <comment>{$apiName}:
{$apiKeys}</comment>
</info>");

		$routingYmlPathname = "Resources/config/routing.yml";
		$routingTemplate = $this->twig->loadTemplate("Resources/config/routing-oauth.yml.twig");
		$routingTemplate = $routingTemplate->render(array(
			"classPrefix" => $className,
			"appName" => explode("-", $appName, 2)[1]
		));

		if (!file_exists($routingYmlPathname)) {
			file_put_contents($routingYmlPathname, $routingTemplate);
			$output->writeln("<info>{$routingYmlPathname} saved</info>");
		} elseif ($dialog->askConfirmation(
			$output,
			"Add 'OAuth API' routing data to existing {$routingYmlPathname}? [<options=bold>y</options=bold>/n]: ",
			true
		)) {
			$yaml = new Yaml();
			$templateData = $yaml->parse($routingTemplate);

			$result = array_replace_recursive($yaml->parse(file_get_contents($routingYmlPathname)), $templateData);
			file_put_contents($routingYmlPathname, $yaml->dump($result, 3));

			$output->writeln("<info>{$routingYmlPathname} saved</info>");
		} else {
			$output->writeln("<error>{$routingYmlPathname} generation skipped</error>");
		}
	}

	protected function checkFileOverwrite($dialog, $output, $filename, $default = false) {
		$defText = $default ? "[<options=bold>y</options=bold>/n]" : "[y/<options=bold>n</options=bold>]";
		if (file_exists($filename)) {
			if (!$dialog->askConfirmation(
				$output,
				"File <comment>{$filename}</comment> already exists, overwrite? {$defText}: ",
				$default
			)) {
				return false;
			}
		}
		return true;
	}

	protected function checkInteractive()
	{
		if ($this->isInteractive) {
			return true;
		} else {
			throw new \Exception("Complete set of commandline arguments must be provided to run in non-interactive mode!");
		}
	}
}
