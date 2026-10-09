<?php declare(strict_types=1);

namespace Tests\Toolkit;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;

/** Opt-in synthetic database on the project's local Docker network only. */
final class LocalMysqlDatabase
{
    private Connection $server;
    private string $name;
    private bool $created = false;

    public function __construct()
    {
        if (!is_file('/.dockerenv')) { throw new \RuntimeException('Local MySQL tests must run inside the project Docker container.'); }
        $this->name = 'bripeon_workflow_test_' . bin2hex(random_bytes(6));
        $this->server = DriverManager::getConnection($this->serverParameters());
        $this->server->executeStatement('CREATE DATABASE `' . $this->name . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->created = true;
        register_shutdown_function([$this, 'cleanup']);
    }

    public function parameters(): array { return $this->serverParameters() + ['dbname' => $this->name]; }

    private function serverParameters(): array
    {
        // Explicit local Compose credentials; application/environment configuration is never loaded.
        return ['driver' => 'pdo_mysql', 'host' => 'database', 'port' => 3306, 'user' => 'root', 'password' => 'root', 'charset' => 'utf8mb4'];
    }

    public function cleanup(): void
    {
        if (!$this->created) { return; }
        $this->server->executeStatement('DROP DATABASE `' . $this->name . '`');
        $this->created = false;
        $this->server->close();
    }
}
