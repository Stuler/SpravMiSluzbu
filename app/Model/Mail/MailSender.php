<?php

namespace App\Model\Mail;

use App\Domain\Provider\ActivationToken;
use Contributte\Mailing\IMailBuilderFactory;

readonly class MailSender
{

	public function __construct(
		private IMailBuilderFactory $mailBuilderFactory,
	)
	{
	}

	public function sendActivationEmailUser(string $to, string $name, string $link): void
	{
		$mail = $this->mailBuilderFactory->create();
		$mail->setFrom('info@spravmisluzbu.sk');
		$mail->addTo($to);
		$mail->setSubject('Account Activation');
		$mail->setTemplateFile(MAIL_TEMPLATES_DIR . 'activation.latte');
		$mail->setParameters([
			'title' => 'Test',
			'content' => $link,
		]);

		$mail->send();
	}

	public function sendCustomerLink(string $to, string $subject, string $message, string $link): void
	{
		$mail = $this->mailBuilderFactory->create();
		$mail->setFrom('info@spravmisluzbu.sk');
		$mail->addTo($to);
		$mail->setSubject($subject);
		$mail->setTemplateFile(MAIL_TEMPLATES_DIR . 'customerLink.latte');
		$mail->setParameters(['message' => $message, 'link' => $link]);
		$mail->send();
	}

	public function sendActivationEmailProvider(string $to, string $name, string $link): void
	{
		$mail = $this->mailBuilderFactory->create();
		$mail->setFrom('info@spravmisluzbu.sk');
		$mail->addTo($to);
		$mail->setSubject('Aktivujte si účet poskytovateľa Bripeon');
		$mail->setTemplateFile(MAIL_TEMPLATES_DIR . 'providerActivation.latte');
		$mail->setParameters([
			'name' => $name,
			'link' => $link,
			'expiresHours' => ActivationToken::TTL_HOURS,
		]);

		$mail->send();
	}
}
