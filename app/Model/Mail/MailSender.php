<?php

namespace App\Model\Mail;

use Contributte\Mailing\IMailBuilderFactory;

readonly class MailSender
{

	public function __construct(
		private IMailBuilderFactory $mailBuilderFactory,
	)
	{
	}

	public function sendActivationEmail(string $to, string $name): void
	{
		$mail = $this->mailBuilderFactory->create();
		$mail->setFrom('info@spravmisluzbu.sk');
		$mail->addTo($to);
		$mail->setSubject('Account Activation');
		$mail->setTemplateFile(MAIL_TEMPLATES_DIR . 'activation.latte');
		$mail->setParameters([
			'title' => 'Test',
			'content' => "Welcome",
		]);

		$mail->send();

	}
}
