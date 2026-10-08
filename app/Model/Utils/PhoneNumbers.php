<?php declare(strict_types=1);

namespace App\Model\Utils;

final class PhoneNumbers
{
	public static function normalize(string $value): string
	{
		$number = preg_replace('/[\s()-]/u', '', $value) ?? '';
		$number = preg_replace('/^00421/', '+421', $number) ?? '';

		return preg_replace('/^0(?=[1-9])/', '+421', $number) ?? '';
	}

	public static function isValid(string $value): bool
	{
		return preg_match('/^\+421[1-9]\d{8}$/D', self::normalize($value)) === 1;
	}
}
