<?php

class R extends RedBean_Facade {
	
	public static function sql() {
		return SqlBuilder::instance();
	}
}