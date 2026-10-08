<?php declare(strict_types = 1);

namespace App\Domain\Http;

use Contributte\Events\Extra\Event\Application\RequestEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Tracy\Debugger;

class RequestLoggerSubscriber implements EventSubscriberInterface
{

	/**
	 * {@inheritdoc}
	 */
	public static function getSubscribedEvents(): array
	{
		return [RequestEvent::class => 'onRequest'];
	}

	public function onRequest(RequestEvent $event): void
	{
		// Request bodies and query parameters may contain passwords and bearer tokens.
		Debugger::barDump(['presenter' => $event->getRequest()->getPresenterName(), 'method' => $event->getRequest()->getMethod()]);
	}

}
