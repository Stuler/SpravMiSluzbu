<?php declare(strict_types=1);

// Real HTTP requests and cookies, synthetic local MySQL data, file mail capture, no AI.
require __DIR__ . '/../../../../vendor/autoload.php';
require __DIR__ . '/../../../../app/bootstrapConstants.php';
\Tester\Environment::setup();
use Tester\Assert;
use App\Domain\CategoryService\CategoryService;
use App\Domain\User\User;
use App\Domain\Provider\Provider;
use App\Domain\Demand\Demand;
use App\Domain\Matching\ProviderInvitation;

if (($argv[1] ?? '') !== '--local-docker' || !is_file('/.dockerenv')) { \Tester\Environment::skip('Opt in with --local-docker.'); }
$database = new \Tests\Toolkit\LocalMysqlDatabase();
$config = \Tests\Toolkit\IsolatedEntityManager::create()->getConfiguration();
$em = new \App\Model\Database\EntityManagerDecorator(\Doctrine\ORM\EntityManager::create($database->parameters(), $config));
$classes = [User::class, Provider::class, Demand::class, ProviderInvitation::class, CategoryService::class,
    \App\Domain\LoginRole\LoginRole::class, \App\Domain\StateUser\StateUser::class, \App\Domain\StateProvider\StateProvider::class,
    \App\Domain\City\City::class, \App\Domain\District\District::class, \App\Domain\Region\Region::class,
    \App\Domain\Customer\CustomerToken::class, \App\Domain\Admin\AdminAudit::class, \App\Domain\Matching\AiRun::class,
    \App\Domain\ProviderRegion\ProviderRegion::class, \App\Domain\ProviderServiceCategory\ProviderServiceCategory::class,
    \App\Domain\Job\Job::class, \App\Domain\Review\Review::class];
(new \Doctrine\ORM\Tools\SchemaTool($em))->createSchema(array_map(fn($class) => $em->getClassMetadata($class), $classes));
foreach (['fresh', 'active', 'blocked'] as $name) {
    $s = new \App\Domain\StateUser\StateUser($name); $s->setDateCreated(new DateTime()); $em->persist($s);
    $p = new \App\Domain\StateProvider\StateProvider(); $p->setName($name); $p->setDateCreated(new DateTime()); $em->persist($p);
}
$roles = [];
foreach (['admin', 'member', 'provider'] as $name) { $r = new \App\Domain\LoginRole\LoginRole(); $r->setName($name); $em->persist($r); $roles[$name] = $r; }
$region = new \App\Domain\Region\Region(); $region->setName('Test region'); $region->setShortcut('TT'); $region->setUse(true); $em->persist($region);
$district = new \App\Domain\District\District(); $district->setName('Test district'); $district->setVehRegNum('TT'); $district->setCode(1); $district->setRegion($region); $district->setUse(true); $em->persist($district);
$city = new \App\Domain\City\City(); $city->setFullname('Test city'); $city->setShortname('Test'); $city->setZip('90001'); $city->setRegion($region); $city->setDistrict($district); $city->setUse(true); $em->persist($city);
$category = new CategoryService('Cleaning'); $em->persist($category); $em->flush();
$password = 'isolated-test-password';
$hash = \App\Model\Security\Passwords::create()->hash($password);
$admin = new User('Test', 'Admin', 'admin@example.test', $hash, $roles['admin'], $em->find(\App\Domain\StateUser\StateUser::class, 2), null, null, null, ''); $em->persist($admin);
$provider = new Provider('Test company', 'Test', 'Provider', '', 'provider@example.test', '+421900000001', '12345678', '', $hash, 'Test 1', $city, '90001', $em->find(\App\Domain\StateProvider\StateProvider::class, 2), $roles['provider']); $em->persist($provider);
$em->flush();
$providerId = $provider->getId();
$temporary = '/tmp/bripeon-http-' . bin2hex(random_bytes(6));
foreach (['', '/log', '/mail', '/sessions'] as $directory) { mkdir($temporary . $directory, 0700, true); }
$socket = stream_socket_server('tcp://127.0.0.1:0');
$address = stream_socket_get_name($socket, false); fclose($socket);
$base = 'http://' . $address;
$process = proc_open([PHP_BINARY, '-S', $address, '-t', APP_DIR . '/../www', __DIR__ . '/../../../Fixtures/Http/router.php'],
    [0 => ['pipe', 'r'], 1 => ['file', $temporary . '/server.log', 'a'], 2 => ['file', $temporary . '/server.log', 'a']], $pipes, null,
    ['BRIPEON_HTTP_DATABASE' => $database->parameters()['dbname'], 'BRIPEON_HTTP_TEMP' => $temporary]);
if (!is_resource($process)) { throw new RuntimeException('Could not start local HTTP server.'); }
fclose($pipes[0]);

$request = function (string $actor, string $path, ?array $post = null) use ($base, $temporary): array {
    $url = str_starts_with($path, 'http://') ? $path : $base . $path;
    if (!str_starts_with($url, $base . '/')) { throw new RuntimeException('Test navigation left the local server.'); }
    $curl = curl_init($url);
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEFILE => $temporary . '/' . $actor . '.cookies', CURLOPT_COOKIEJAR => $temporary . '/' . $actor . '.cookies', CURLOPT_TIMEOUT => 15]);
    if ($post !== null) { curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($post), CURLOPT_HTTPHEADER => ['Sec-Fetch-Site: same-origin', 'Origin: ' . $base]]); }
    $html = curl_exec($curl); $status = curl_getinfo($curl, CURLINFO_HTTP_CODE); $redirect = curl_getinfo($curl, CURLINFO_REDIRECT_URL); curl_close($curl);
    if (!is_string($html) || $status >= 500) { throw new RuntimeException('HTTP test request failed: ' . $path . ' (' . $status . ')'); }
    return [$status, $html, $redirect];
};
$submit = function (string $actor, string $path, string $formId, array $values) use ($request): array {
    [$status, $html] = $request($actor, $path); Assert::same(200, $status);
    $dom = new DOMDocument(); @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    $xpath = new DOMXPath($dom); $form = $xpath->query('//form[@id="' . $formId . '"]')->item(0);
    if (!$form instanceof DOMElement) { throw new RuntimeException('Missing form: ' . $formId); }
    $data = [];
    foreach ($xpath->query('.//input[@type="hidden"]', $form) as $input) { $data[$input->getAttribute('name')] = $input->getAttribute('value'); }
    $action = $form->getAttribute('action');
    if ($action === '' || $action[0] === '?') { $action = $path . $action; }
    return $request($actor, $action, array_replace($data, $values, ['submit' => 'Submit']));
};

$passed = false;
try {
    for ($i = 0; $i < 50; $i++) { $ready = @fsockopen('127.0.0.1', (int) substr(strrchr($address, ':'), 1)); if ($ready) { fclose($ready); break; } usleep(100000); }
    [$status] = $submit('admin', '/admin/sign/in', 'frm-loginForm', ['email' => 'admin@example.test', 'password' => $password]); Assert::same(303, $status);
    $profile = ['companyName' => 'Updated company', 'contactName' => 'Test', 'surname' => 'Provider', 'title' => '', 'phone' => '+421900000001', 'ico' => '12345678', 'dic' => '', 'streetNo' => 'Test 1', 'zipCode' => '90001', 'city' => $city->getId(), 'description' => 'Cleaning homes', 'accepts' => '1', 'categories' => [$category->getId()], 'regions' => [$region->getId()]];
    [$status] = $submit('admin', '/admin/provider/detail/' . $providerId, 'frm-profileForm', $profile); Assert::same(303, $status);
    [$status] = $submit('guest', '/demand/create', 'frm-demandForm', ['description' => 'Please clean my two bedroom flat.', 'cityId' => $city->getId(), 'categoryId' => $category->getId(), 'requestedTerm' => 'Next week', 'contactName' => 'Test Customer', 'contactEmail' => 'customer@example.test', 'contactPhone' => '']); Assert::same(303, $status);
    $messages = glob($temporary . '/mail/*'); Assert::count(1, $messages);
    $mail = html_entity_decode(file_get_contents($messages[0]));
    Assert::same(1, preg_match('#http://127\.0\.0\.1:[0-9]+/demand/access\?token=[a-f0-9]+#', $mail, $match));
    [$status, , $detail] = $request('guest', $match[0]); Assert::same(302, $status);
    [$status] = $submit('guest', $detail, 'frm-verifyForm', []); Assert::same(303, $status);
    $em->clear(); $demand = $em->getRepository(Demand::class)->findOneBy(['contactEmail' => 'customer@example.test']);
    Assert::same(Demand::READY, $demand->getStatus());
    $adminDetail = '/admin/demand/detail?reference=' . $demand->getReference();
    [$status] = $submit('admin', $adminDetail, 'frm-approveForm', ['providers' => [$providerId]]); Assert::same(303, $status);

    // The actual invitation transport writes a complete email into the isolated mailbox.
    $mailer = new \Tests\Toolkit\TestMailer($temporary . '/mail');
    $builderFactory = new class($mailer) implements \Contributte\Mailing\IMailBuilderFactory {
        public function __construct(private \Nette\Mail\Mailer $mailer) {}
        public function create(): \Contributte\Mailing\MailBuilder {
            $sender = new \Contributte\Mailing\NetteMailSender($this->mailer);
            $builder = new \Contributte\Mailing\MailBuilder($sender);
            $template = new \Nette\Bridges\ApplicationLatte\DefaultTemplate(new \Latte\Engine()); $template->_config = (object) ['layout' => false]; $builder->setTemplate($template); return $builder;
        }
    };
    $links = new \Nette\Application\LinkGenerator((new \App\Model\Router\RouterFactory())->create(), new \Nette\Http\UrlScript($base . '/'));
    $invitations = new \App\Domain\Matching\InvitationFacade($em, new \App\Model\Mail\MailSender($builderFactory), $links,
        new \App\Infrastructure\Persistence\Doctrine\DoctrineInvitationRepository($em),
        new \App\Infrastructure\Persistence\Doctrine\DoctrineDemandRepository($em),
        new \App\Infrastructure\Persistence\Doctrine\DoctrineProviderRepository($em));
    Assert::true($invitations->deliverOne());
    Assert::count(2, glob($temporary . '/mail/*'));
    $invitation = $em->getRepository(ProviderInvitation::class)->findOneBy(['demand' => $demand]);
    [$status] = $submit('provider', '/provider-sign/in', 'frm-signInForm', ['email' => 'provider@example.test', 'password' => $password]); Assert::same(303, $status);
    $providerDetail = '/provider-demand/detail/' . $invitation->getId();
    [$status, $html] = $request('provider', $providerDetail); Assert::same(200, $status); Assert::notContains('customer@example.test', $html);
    [$status] = $submit('provider', $providerDetail, 'frm-responseForm', ['accept' => '1']); Assert::same(303, $status);
    [, $html] = $request('provider', $providerDetail); Assert::contains('customer@example.test', $html);
    [, $html] = $request('admin', $adminDetail); Assert::contains('Má záujem', $html);
    echo "HTTP admin edit -> guest demand -> captured verification mail -> manual selection -> captured invitation -> provider response -> admin review passed.\n";
    $passed = true;
} finally {
    proc_terminate($process); proc_close($process);
    $em->getConnection()->close(); $database->cleanup();
    // Delete only the directory created by this invocation; retain synthetic failure diagnostics.
    if ($passed && preg_match('#^/tmp/bripeon-http-[a-f0-9]{12}$#D', $temporary)) {
        \Nette\Utils\FileSystem::delete($temporary);
    }
}
