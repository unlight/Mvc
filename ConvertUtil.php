<?php

class ConvertUtil {

	public static function __callStatic($name, $args) {
		$id = $args[0];
		$model = self::getModel($name);
		if (!$model instanceof Model) {
			throw new Exception("Cannot find model '$name'.");
		}
		return $model->getId($id);
	}

	public static function getModel($name) {
		static $models;
		$nameModel = ucfirst($name) . 'Model';
		$model =& $models[$name];
		if (is_null($model)) {
			$lowerName = strtolower($name);
			$model = new $nameModel($lowerName);
		}
		return $model;
	}
}