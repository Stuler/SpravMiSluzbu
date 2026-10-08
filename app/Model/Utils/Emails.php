<?php declare(strict_types = 1);

namespace App\Model\Utils;

final class Emails
{

	/**
	 * Normalize an email address for storage and lookup.
	 * Trims surrounding whitespace and lowercases the whole address.
	 */
	public static function normalize(string $email): string
	{
		return mb_strtolower(trim($email));
	}

}
