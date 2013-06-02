<?php

class BeanModelFormatter implements RedBean_IModelFormatter {

	public function formatModel($model) {
		$parts = explode('_', $model);
		$parts = array_map('ucfirst', $parts);
		$name = implode('', $parts);
		return $name . 'BeanModel';
	}
}