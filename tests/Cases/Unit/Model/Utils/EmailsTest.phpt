<?php declare(strict_types = 1);

use App\Model\Utils\Emails;
use Tester\Assert;

require_once __DIR__ . '/../../../../bootstrap.php';

test(function (): void {
	Assert::same('user@example.com', Emails::normalize('User@Example.com'));
	Assert::same('user@example.com', Emails::normalize('  user@example.com  '));
	Assert::same('user@example.com', Emails::normalize('USER@EXAMPLE.COM'));
	Assert::same('user@example.com', Emails::normalize("\tUser@Example.com\n"));
	Assert::same('already@normal.sk', Emails::normalize('already@normal.sk'));
});
