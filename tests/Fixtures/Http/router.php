<?php declare(strict_types=1);

// Only an explicitly started, loopback-only test server may use this entry point.
require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../../app/bootstrapConstants.php';
$database = getenv('BRIPEON_HTTP_DATABASE') ?: '';
$temporary = getenv('BRIPEON_HTTP_TEMP') ?: '';
if (!is_file('/.dockerenv') || !preg_match('/^bripeon_workflow_test_[a-f0-9]{12}$/D', $database)
    || !preg_match('#^/tmp/bripeon-http-[a-f0-9]{12}$#D', $temporary)) {
    http_response_code(403); exit;
}
$root = dirname(__DIR__, 3);
$configurator = new \Contributte\Bootstrap\ExtraConfigurator();
$configurator->setTempDirectory($temporary);
$configurator->setDebugMode(false);
$configurator->enableTracy($temporary . '/log');
$configurator->addStaticParameters(['rootDir' => $root, 'appDir' => $root . '/app', 'wwwDir' => $root . '/www', 'logDir' => $temporary . '/log', 'scope' => 'web']);
$configurator->addConfig($root . '/config/env/dev.neon');
$configurator->addConfig([
    'parameters' => ['database' => ['driver' => 'pdo_mysql', 'host' => 'database', 'port' => 3306, 'dbname' => $database, 'user' => 'root', 'password' => 'root'], 'system' => ['error' => ['email' => null]]],
    'services' => ['mail.mailer' => new \Nette\DI\Definitions\Statement(\Tests\Toolkit\TestMailer::class, [$temporary . '/mail'])],
    'session' => ['savePath' => $temporary . '/sessions'],
    'application' => ['catchExceptions' => false],
]);
$configurator->createContainer()->getByType(\Nette\Application\Application::class)->run();
