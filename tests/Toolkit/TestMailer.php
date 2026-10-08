<?php declare(strict_types = 1);

namespace Tests\Toolkit;

use Contributte\Mail\Mailer\FileMailer;

/**
 * Writes emails to files (like FileMailer) and tolerates the setBounceMail() setup
 * call configured for the real mailer, so tests never invoke sendmail.
 */
final class TestMailer extends FileMailer
{

	public function setBounceMail(string $bounceMail): void
	{
		// No-op in tests.
	}

}
