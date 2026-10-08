<?php declare(strict_types=1);

use App\Model\Utils\PhoneNumbers;
use Tester\Assert;

require_once __DIR__ . '/../../../../bootstrap.php';

test(function (): void {
	foreach (['+421 905 123 456', '0905 123 456', '00421 (905) 123-456'] as $number) {
		Assert::same('+421905123456', PhoneNumbers::normalize($number));
		Assert::true(PhoneNumbers::isValid($number));
	}
	Assert::same('+421212345678', PhoneNumbers::normalize('02 1234 5678'));
	Assert::true(PhoneNumbers::isValid('02 1234 5678'));
	foreach (['', '123', '+420905123456', '00420905123456', '+4219051234567', 'call me'] as $number) {
		Assert::false(PhoneNumbers::isValid($number));
	}
});
