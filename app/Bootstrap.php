<?php declare(strict_types=1);

namespace App;

use Contributte\Bootstrap\ExtraConfigurator;
use Nette\Application\Application as NetteApplication;
use Nette\DI\Compiler;
use Symfony\Component\Console\Application as SymfonyApplication;
use Tracy\Debugger;

require 'bootstrapConstants.php';

final class Bootstrap
{

	public static function boot(): ExtraConfigurator
	{
		$configurator = new ExtraConfigurator();
		$configurator->setTempDirectory(__DIR__ . '/../var/tmp');

		$configurator->onCompile[] = function (ExtraConfigurator $configurator, Compiler $compiler): void {
			// Add env variables to config structure
			$compiler->addConfig(['parameters' => $configurator->getEnvironmentParameters()]);
		};

		$configurator->setDebugMode(['192.168.0.201', '192.168.2.6', '100.126.20.71']);

		// Enable tracy and configure it
		$configurator->enableTracy(__DIR__ . '/../var/log');
		Debugger::$errorTemplate = __DIR__ . '/../resources/tracy/500.phtml';

		// Provide some parameters
		$configurator->addStaticParameters([
			'rootDir' => realpath(__DIR__ . '/..'),
			'appDir' => __DIR__,
			'wwwDir' => realpath(__DIR__ . '/../www'),
			'logDir' => realpath(__DIR__ . '/../var/log'),
		]);

		// Load development or production config
		if (getenv('NETTE_ENV', true) === 'dev') {
			$configurator->addConfig(__DIR__ . '/../config/env/dev.neon');
		} else {
			$configurator->addConfig(__DIR__ . '/../config/env/prod.neon');
		}

		$configurator->addConfig(__DIR__ . '/../config/local.neon');

		return $configurator;
	}

	public static function runWeb(): void
	{
		self::boot()
			->addStaticParameters([
				'scope' => 'web',
			])
			->createContainer()
			->getByType(NetteApplication::class)
			->run();
	}

	public static function runCli(): void
	{
		self::boot()
			->addStaticParameters([
				'scope' => 'cli',
			])
			->createContainer()
			->getByType(SymfonyApplication::class)
			->run();
	}

}
