<?php

use Silex\ServiceProviderInterface;
use Silex\Application;

class FormServiceProvider implements ServiceProviderInterface {

	/**
	 * Registers services on the given app.
	 *
	 * This method should only be used to configure services and parameters.
	 * It should not get services.
	 *
	 * @param Application $app An Application instance
	 */
	public function register(Application $app) {
		// Form.
		$app['form'] = function() use ($app) {
			$form = new Form($app['config'], $app['session.handler']);
			return $form;
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