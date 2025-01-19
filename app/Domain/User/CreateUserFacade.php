<?php declare(strict_types=1);

namespace App\Domain\User;

use App\Domain\LoginRole\LoginRole;
use App\Domain\StateUser\StateUser;
use App\Domain\StateUser\StateUserRepository;
use App\Model\Database\EntityManagerDecorator;
use App\Model\Mail\MailSender;
use App\Model\Security\Passwords;
use Exception;

readonly class CreateUserFacade
{


	public function __construct(
		private EntityManagerDecorator $em,
		private MailSender             $mailSender,
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
		);
		$this->em->persist($user);
		$this->em->flush();

		$this->mailSender->sendActivationEmail($user->getEmail(), $user->getName());

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

}
