<?php

class Logger implements RedBean_Logger {
	/**
	* Default logger method logging to STDOUT.
	* This is the default/reference implementation of a logger.
	* This method will write the message value to STDOUT (screen).
	*
	* @param $message (optional)
	*/
	public function log() {
		$sql = func_get_arg(0);
		$values = null;
		if (func_num_args() > 1) $values = func_get_arg(1);
		if (is_array($values) && count($values) > 0) {
			$sql = preg_replace_callback('/\?/', function($matches) use ($values) {
				return array_shift($values);
			}, $sql);
		}
	}

}