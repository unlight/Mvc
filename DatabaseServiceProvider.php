<?php

use Symfony\Component\HttpFoundation\Response;
use Silex\ServiceProviderInterface;
use Silex\Application;

class DatabaseServiceProvider implements ServiceProviderInterface {

	protected $app;
	public $logger;

	public function update(Application $app) {
		$accessUser = $app['config']->get('database.structure.access.user', 'root');
		$accessPassword = $app['config']->get('database.structure.access.password', rand());

		$request = $app['request'];
		$username = $request->server->get('PHP_AUTH_USER', false);
		$password = $request->server->get('PHP_AUTH_PW');

		$response = new Response();

		if (($username == $accessUser && $password == $accessPassword) 
			|| $accessPassword == $request->get('accessPassword')) {
			$this->doUpdate();
			// Dump logs.
			$response->setContent("<pre>" . implode("\n", $this->logger->getLogs()) . "</pre>");
			return $response;
		}
		
		$response->headers->set('WWW-Authenticate', sprintf('Basic realm="%s"', ''));
		$response->setStatusCode(401, 'Please sign in.');
		return $response;
	}

	public static function runUpdateConsole() {
		if (PHP_SAPI != 'cli') throw new Exception("Error Processing Request", 1);
		$app = new Silex\Application();
		$app->register(new ConfigurationServiceProvider('settings'));
		$databaseServiceProvider = new self();
		$databaseServiceProvider->boot($app);
		$databaseServiceProvider->doUpdate();
		$count = 0;
		foreach ($databaseServiceProvider->logger->getLogs() as $log) {
			$count++;
			echo sprintf("%02d. %s\n", $count, trim($log));
		}
	}

	public function doUpdate() {
		$this->logger = RedBean_Plugin_QueryLogger::getInstanceAndAttach(R::$adapter);
		RedBean_ModelHelper::setModelFormatter(null);
		R::freeze(false);
		$structureFile = 'settings/structure.php';
		if (file_exists($structureFile)) {
			include $structureFile;
		}
		$this->cleanUp();
		R::freeze(true);
	}

	public static function cleanUp() {
		foreach (R::getWriter()->getTables() as $table) {
			$columns = R::getColumns($table);
			unset($columns['id']);
			$condition = '';
			if (count($columns) == 0) {
				continue;
			}
			foreach (array_keys($columns) as $index => $keyName) {
				if ($index > 0) $condition .= " and ";
				$condition .= "`$keyName` is null";
			}
			$sql = "select count(*) from `$table` where $condition";
			$count = R::exec($sql);
			if ($count > 0) {
				// limit $count
				R::exec("delete from `$table` where $condition");
			}
		}
	}

	/**
	 * Registers services on the given app.
	 *
	 * This method should only be used to configure services and parameters.
	 * It should not get services.
	 *
	 * @param Application $app An Application instance
	 */
	public function register(Application $app) {
		$this->app = $app;
		$app->match('/structure/update', array($this, 'update'));
	}

	/**
	 * Bootstraps the application.
	 *
	 * This method is called after all services are registers
	 * and should be used for "dynamic" configuration (whenever
	 * a service must be requested).
	 */
	public function boot(Application $app) {
		$dsn = $app['config']->get('database.dsn');
		if (!$dsn) {
			$dsn = $app['config']->get('database.engine') . ':host=' . $app['config']->get('database.host') . ';dbname=' . $app['config']->get('database.name');
		}
		$toolbox = R::setup($dsn, $app['config']->get('database.user'), $app['config']->get('database.password'));
		R::$writer->setUseCache(true);
		// R::setRedBean(new RedBean_Plugin_Cache(R::$writer));
		R::freeze(true);
		RedBean_ModelHelper::setModelFormatter(new ModelFormatter());
		// R::debug(true);
		// R::$adapter->addEventListener('sql_exec', new QueryLogger());
		// R::debug(true, new Logger());
	}
}