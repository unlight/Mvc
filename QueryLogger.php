<?php 

class QueryLogger extends RedBean_Plugin_QueryLogger {

	public function __construct() {
	}
	
	public function onEvent($eventName, $adapter) {
		$method = $eventName . '_handler';
		if (method_exists($this, $method)) {
			$this->$method($adapter);
		}
	}

	public function sql_exec_handler($adapter) {
		d(func_get_args());
	}
}