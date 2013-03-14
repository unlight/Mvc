<?php

use Silex\ServiceProviderInterface;
use Silex\Application;

class ValidationServiceProvider implements ServiceProviderInterface {

	/**
	 * Registers services on the given app.
	 *
	 * This method should only be used to configure services and parameters.
	 * It should not get services.
	 *
	 * @param Application $app An Application instance
	 */
	public function register(Application $app) {
		// Validation.
		$app['validation'] = function() use ($app) {
			$validation = new Validation($app['config']);
			return $validation;
		};
	}

	/**
	 * Bootstraps the application.
	 *
	 * This method is called after all services are registers
	 * and should be used for "dynamic" configuration (whenever
	 * a service must be requested).
	 */
	public function boot(Application $app) {
		// Load custom functions.
		LoadFunctions('Request');
	}
}