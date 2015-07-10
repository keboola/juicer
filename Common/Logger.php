<?php

namespace Keboola\Juicer\Common;

use	Keboola\Juicer\Exception\ApplicationException as Exception;
use	Monolog\Logger as Monolog;

/**
 * Wrapper for Monolog\Logger
 * @see \Monolog\Logger
 */
class Logger {
	private static $logger = null;
	private static $strict = true;

	/**
	 *	Ensure the class doesn't get instantinated
	 */
	private function __construct() {}

	public static function setLogger(Monolog $logger) {
		self::$logger = $logger;
	}

	/**
	 * Set whether to fail or ignore the logging when no logger is set.
	 * @param bool $bool
	 */
	public static function setStrict($bool) {
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
	public static function log($level, $message, array $context = array()) {
		if (self::$logger == null) {
			if (self::$strict) {
				$e = new Exception(500, "Logger has not been set!");
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
