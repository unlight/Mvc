<?php

class ModelFormatter implements RedBean_IModelFormatter {

	public function formatModel($model) {
		return ucfirst($model) . 'Model';
	}
}
