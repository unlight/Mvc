<?php

class BeanFormatter implements RedBean_IModelFormatter {

	public function formatModel($model) {
		return ucfirst($model) . 'Bean';
	}
}
