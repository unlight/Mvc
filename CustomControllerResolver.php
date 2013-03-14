<?php

use Silex\ControllerResolver;
use Symfony\Component\HttpFoundation\Request;

class CustomControllerResolver extends ControllerResolver {

	public function getController(Request $request) {
		$result = parent::getController($request);
		
		list($controller, $method) = $result;
		if ($controller instanceof Controller) {
			$controller->requestMethodName($method);
			// $deliveryType = $request->request->get('deliveryType');
			// if (!$deliveryType) $deliveryType = $request->get('deliveryType', 'ALL');
			$deliveryType = GetIncomingValue('deliveryType', 'ALL');
			$controller->deliveryType($deliveryType);
		}

		return $result;
	}
}