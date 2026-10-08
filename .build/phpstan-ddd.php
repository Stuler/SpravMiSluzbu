<?php declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

// Metadata-only analysis: never boot the configured application database.
return \Tests\Toolkit\IsolatedEntityManager::create();
