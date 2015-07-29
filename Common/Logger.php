<?php

namespace Keboola\Juicer\Common;

use	Keboola\Juicer\Exception\ApplicationException as Exception;
use	Monolog\Logger as Monolog,
	Monolog\Handler\StreamHandler,
	Monolog\Formatter\LineFormatter;

/**
 * Wrapper for Monolog\Logger
 * @see \Monolog\Logger
 */
class Logger
{
	/**
	 * @var Monolog
	 */
	private static $logger = null;
	/**
	 * @var bool
	 */
	private static $strict = true;

	/**
	 *	Ensure the class doesn't get instantinated
	 */
	private function __construct() {}

	public static function setLogger(Monolog $logger)
	{
		self::$logger = $logger;
	}

	/**
	 * @return Monolog
	 */
	public static function getLogger()
	{
		return self::$logger;
	}

	/**
	 * @param string $name
	 * @param bool $debug true|false|null where null uses the default behavior (--debug cli parameter)
	 */
	public static function initLogger($name = '', $debug = null)
	{
		$options = getopt("", ['debug']);
		if (is_null($debug)) {
			$debug = isset($options['debug']);
		}
		$level = $debug ? Monolog::DEBUG : Monolog::INFO;

		$handler = new StreamHandler('php://stdout', $level);
		// Print out less verbose messages out of debug
		if ($level != Monolog::DEBUG) {
			$handler->setFormatter(new LineFormatter("%message%\n"));
		}
		self::$logger = new Monolog($name, [$handler]);
	}

	/**
	 * Set whether to fail or ignore the logging when no logger is set.
	 * @param bool $bool
	 */
	public static function setStrict($bool)
	{
		self::$strict = (bool) $bool;
	}

	/**
	 * @see \Monolog\Logger::log()
	 *
	 * @param string $level [debug,info,notice,warning,error,critical,alert,emergency]
	 * @param string $message
	 * @param array $context
	 * @return bool
	 */
	public static function log($level, $message, array $context = array())
	{
		if (self::$logger == null) {
			if (self::$strict) {
				$e = new Exception("Logger has not been set!");
				$e->setData(array(
					"level" => $level,
					"message" => $message,
					"context" => $context
				));
				throw $e;
			} else {
				return false;
			}
		}

		return self::$logger->log($level, $message, $context);
	}
}
