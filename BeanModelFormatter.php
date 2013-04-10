<?php

class BeanModelFormatter implements RedBean_IModelFormatter {

	public function formatModel($model) {
		return ucfirst($model) . 'BeanModel';
	}
}
