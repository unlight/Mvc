<?php

// TODO: REMOVE CLASS

class Column {
	
	public static function String($Length = 250) {
		return str_repeat('*', $Length);
	}

	public static function Text() {
		return self::String(65536 - 1);
	}

	public static function LongText() {
		return self::String(65536 + 1);
	}

	public static function Float() {
		return 9.99;
	}

	public static function Int() {
		return 1000;
	}

	public static function Date() {
		return date('Y-m-d');
	}

	public static function DateTime() {
		return date('Y-m-d H:i:s');
	}
}