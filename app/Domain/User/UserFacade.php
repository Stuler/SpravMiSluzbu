<?php declare(strict_types=1);

namespace App\Domain\User;

use App\Domain\LoginRole\LoginRole;
use App\Domain\StateUser\StateUser;
use App\Domain\StateUser\StateUserRepository;
use App\Model\Database\EntityManagerDecorator;
use App\Model\Exception\Logic\InvalidArgumentException;
use App\Model\Exception\Logic\UserAlreadyActiveException;
use App\Model\Mail\MailSender;
use App\Model\Security\Passwords;
use Exception;
use Nette\Application\LinkGenerator;

readonly class UserFacade
{


	public function __construct(
		private EntityManagerDecorator $em,
		private MailSender             $mailSender,
		private LinkGenerator          $linkGenerator,
	)
	{
	}

	/**
	 * @param array<string, scalar> $data
	 * @throws Exception
	 */
	public function createUser(array $data): User
	{
		$this->validateInputs($data);

		$loginRole = $data['role'] ?? LoginRole::ROLE_MEMBER;
		$loginRoleEntity = $this->em->getRepository(LoginRole::class)->findOneBy(['name' => $loginRole]);
		$stateUserFresh = $this->em->getRepository(StateUser::class)->findOneBy(['id' => StateUserRepository::STATE_FRESH]);
		$user = new User(
			name: (string)$data['name'],
			surname: (string)$data['surname'],
			email: (string)$data['email'],
			password: Passwords::create()->hash(strval($data['password'] ?? md5(microtime()))),
			loginRole: $loginRoleEntity,
			stateUser: $stateUserFresh,
			streetNo: (string)$data['street'],
			city: (string)$data['city'],
			zipCode: (string)$data['zipCode'],
			hash: md5(microtime()),
		);
		$this->em->persist($user);
		$this->em->flush();

		//generate link to Users:activate
		$link = $this->linkGenerator->link('Front:UserSign:activateUser', ['hash' => $user->getHash()]);

		$this->mailSender->sendActivationEmail($user->getEmail(), $user->getName(), $link);

		return $user;
	}

	/**
	 * @throws Exception
	 */
	private function validateInputs(array $data): void
	{
		if ($data['password'] !== $data['password2']) {
			throw new Exception('Passwords do not match');
		}

		$existingUser = $this->em->getRepository(User::class)->findOneBy(['email' => $data['email']]);
		if ($existingUser) {
			throw new Exception('User with this email already exists');
		}
	}

	/**
	 * @throws Exception
	 */
	public function activateUser(string $hash): void
	{
		$user = $this->em->getRepository(User::class)->findOneBy(['hash' => $hash]);
		if (!$user) {
			throw new InvalidArgumentException('User with hash ' . $hash . ' not found');
		}
		if ($user->getStateUser()->getId() === StateUserRepository::STATE_ACTIVATED) {
			throw new UserAlreadyActiveException('User is already activated');
		}

		$stateActivated = $this->em->getRepository(StateUser::class)
			->findOneBy(['id' => StateUserRepository::STATE_ACTIVATED]);

		$user->setStateUser($stateActivated);
		$user->setDateActivated();
		$this->em->persist($user);
		$this->em->flush();
	}

}
