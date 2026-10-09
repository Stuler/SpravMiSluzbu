<?php declare(strict_types=1);

require __DIR__ . '/../../../../vendor/autoload.php';
require __DIR__ . '/../../../../app/bootstrapConstants.php';
\Tester\Environment::setup();

$classes = [
    \App\Domain\Job\JobFacade::class,
    \App\Domain\Job\JobNotifications::class,
    \App\Domain\Review\ReviewFacade::class,
    \App\Infrastructure\Persistence\Doctrine\DoctrineJobRepository::class,
    \App\Infrastructure\Persistence\Doctrine\DoctrineReviewRepository::class,
    \App\Domain\Customer\CustomerAccountFacade::class,
    \App\Domain\Demand\DemandFacade::class,
    \App\Domain\Matching\MatchingFacade::class,
    \App\Domain\Matching\InvitationFacade::class,
    \App\Domain\Provider\ProviderFacade::class,
    \App\Domain\Admin\AdminAuditFacade::class,
    \App\Domain\Admin\AdminDemandFacade::class,
    \App\Domain\Admin\AdminAccountFacade::class,
    \App\Infrastructure\Persistence\Doctrine\DoctrineDemandRepository::class,
    \App\Infrastructure\Persistence\Doctrine\DoctrineProviderRepository::class,
    \App\Infrastructure\Persistence\Doctrine\DoctrineInvitationRepository::class,
];
$projectRoot = dirname(__DIR__, 4);
$configuration = (new \Nette\DI\Config\Adapters\NeonAdapter())->load($projectRoot . '/config/app/services.neon');
$services = [];
foreach ($configuration['services'] as $definition) {
    $class = $definition instanceof \Nette\DI\Definitions\Statement ? $definition->getEntity() : $definition;
    if (is_string($class) && in_array($class, $classes, true)) { $services[] = $definition; }
}
\Tester\Assert::count(count($classes), $services);
$services['em'] = ['factory' => [\Tests\Toolkit\IsolatedEntityManager::class, 'create']];
$services['mailFactory'] = ['factory' => [\Tests\Toolkit\DddServiceFixtures::class, 'mailFactory']];
$services['mail'] = \App\Model\Mail\MailSender::class;
$services['links'] = ['factory' => [\Tests\Toolkit\DddServiceFixtures::class, 'links']];
$services['security'] = ['factory' => [\Tests\Toolkit\DddServiceFixtures::class, 'security']];
$services['ai'] = ['factory' => [\Tests\Toolkit\DddServiceFixtures::class, 'ai']];
$compiler = new \Nette\DI\Compiler();
$compiler->setClassName('DddSmokeContainer');
$compiler->addConfig(['parameters' => ['rootDir' => $projectRoot], 'services' => $services]);
eval(preg_replace('/^<\?php\s*/', '', $compiler->compile()));
$container = new DddSmokeContainer();
foreach ($classes as $class) { \Tester\Assert::type($class, $container->getByType($class)); }
\Tester\Assert::type(\App\Domain\Demand\DemandRepository::class, $container->getByType(\App\Domain\Demand\DemandRepository::class));
echo "Actual domain service configuration compiles and autowires in isolation.\n";
