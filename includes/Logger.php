<?php

/**
 * Logger Class.
 *
 * Author:          Uriahs Victor
 * Created on:      14/07/2023 (d/m/y)
 *
 * @link    https://uriahsvictor.com
 * @since   1.2.0
 * @package CoachFreem
 */

namespace CoachFreem;

/**
 * Class Logger. 
 * 
 * @package CoachFreem
 * @since 1.2.0
 */
class Logger
{

	/**
	 * Log a message.
	 * 
	 * @param string $msg 
	 * @param string $webhook 
	 * @return void 
	 * @since 1.2.0
	 */
	public static function log(string $msg, $webhook = '')
	{
		$bt = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 1);
		$caller = array_shift($bt);

		if (!empty($webhook)) {
			$filename = "webhook-" . time();
			file_put_contents("./logs/$filename.json", json_encode($webhook, JSON_PRETTY_PRINT) . PHP_EOL, FILE_APPEND | LOCK_EX);
		}

		$line = $caller['line'];
		$file = basename($caller['file']);
		$date = date("d-m-y h:i:s A");
		$txt = "[Date(d-m-y): $date][File: $file][Line: $line]- $msg";

		if (!empty($filename)) {
			$txt = "[Webhook ref file: $filename.json]" . $txt;
		}

		file_put_contents("./logs/log.txt", $txt . PHP_EOL, FILE_APPEND | LOCK_EX);
	}
}
