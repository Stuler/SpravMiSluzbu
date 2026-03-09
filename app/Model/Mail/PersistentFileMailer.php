<?php declare(strict_types = 1);

namespace App\Model\Mail;

use Nextras\MailPanel\FileMailer;

final class PersistentFileMailer extends FileMailer
{
	public function setBounceMail(string $bounceMail): void
	{
		// Development mail is persisted to disk, so bounce handling is intentionally ignored.
	}
}
