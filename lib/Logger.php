<?php
require __DIR__ . '/analog/lib/Analog.php';

class Logger {

	const DEBUG = 4;
	const INFO = 3;
	const WARNING = 2;
	const ERROR = 1;

	public static int $log_level;

	function __construct() {
		Analog::handler(Analog\Handler\Stderr::init ());
	}

	function set_level($log_level) {
		if ($log_level < 0 ) $log_level = 0;
		if ($log_level > Logger::DEBUG ) $log_level = Logger::DEBUG;
		Logger::$log_level = $log_level;
	}

	/**
	 * Interpolates context values into the message placeholders.
	 */
	private static function interpolate ($message, array $context = array ()) {
		if (is_array ($message)) {
			return print_r($message, true);
		}

		// build a replacement array with braces around the context keys
		$replace = array ();
		foreach ($context as $key => $val) {
			if (is_object ($val) && get_class ($val) === 'DateTime') {
				$val = $val->format ('Y-m-d H:i:s');
			} elseif (is_object ($val)) {
				$val = json_encode ($val);
			} elseif (is_array ($val)) {
				$val = json_encode ($val);
			} elseif (is_resource ($val)) {
				$val = (string) $val;
			}
			$replace['{' . $key . '}'] = $val;
		}

		// interpolate replacement values into the the message and return
		return strtr ($message, $replace);
	}

	static function set_file($log_file) {
		Analog::handler(Analog\Handler\File::init ($log_file));
	}

	static function debug($message, array $context = array ()) {
		if (Logger::$log_level < Logger::DEBUG) return;
		$message = Logger::interpolate($message, $context);
		Analog::debug($message);
	}

	static function info($message, array $context = array ()) {
		if (Logger::$log_level < Logger::INFO) return;
		$message = Logger::interpolate($message, $context);
		Analog::info($message);
	}

	static function warning($message, array $context = array ()) {
		if (Logger::$log_level < Logger::WARNING) return;
		$message = Logger::interpolate($message, $context);
		Analog::warning($message);
	}

	static function error($message, array $context = array ()) {
		if (Logger::$log_level < Logger::ERROR) return;
		$message = Logger::interpolate($message, $context);
		Analog::error($message);
	}
}