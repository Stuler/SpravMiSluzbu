<?php declare(strict_types=1);

namespace Tests\Toolkit;

final class DddServiceFixtures
{
    public static function mailFactory(): \Contributte\Mailing\IMailBuilderFactory
    {
        return new class implements \Contributte\Mailing\IMailBuilderFactory {
            public function create(): \Contributte\Mailing\MailBuilder
            { throw new \LogicException('Mail delivery is disabled in the DI smoke test.'); }
        };
    }
    public static function links(): \Nette\Application\LinkGenerator
    {
        return new \Nette\Application\LinkGenerator((new \App\Model\Router\RouterFactory())->create(),
            new \Nette\Http\UrlScript('https://test.invalid/'));
    }
    public static function security(): \App\Model\Security\SecurityUser
    { return new \App\Model\Security\SecurityUser(new \Tests\Fixtures\Dummy\DummyUserStorage()); }
    public static function ai(): \App\Domain\Matching\StructuredAiClient
    {
        return new class implements \App\Domain\Matching\StructuredAiClient {
            public function complete(string $prompt, array $input, array $schema): array
            { throw new \LogicException('Network access is disabled in the DI smoke test.'); }
        };
    }
}
