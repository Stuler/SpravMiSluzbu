<?php declare(strict_types = 1);

namespace App\Domain\User;

use App\Domain\LoginRole\LoginRole;
use App\Model\Database\EntityManagerDecorator;
use App\Model\Mail\MailSender;
use App\Model\Security\Passwords;

readonly class CreateUserFacade
{


	public function __construct(
		private EntityManagerDecorator $em,
		private MailSender $mailSender,
	)
	{
	}

	/**
	 * @param array<string, scalar> $data
	 * @throws \Exception
	 */
	public function createUser(array $data): User
	{
		$this->validateInputs($data);

		$loginRole = $data['role'] ?? User::ROLE_MEMBER;
		$loginRoleEntity = $this->em->getRepository(LoginRole::class)->findOneBy(['name' => $loginRole]);
		$user = new User(
			name: (string) $data['name'],
			surname: (string) $data['surname'],
			email: (string) $data['email'],
			passwordHash: Passwords::create()->hash(strval($data['password'] ?? md5(microtime()))),
			streetNo: (string) $data['street'],
			city: (string) $data['city'],
			zipCode: (string) $data['zipCode'],
			loginRole: $loginRoleEntity,
		);
		$user->activate();

		$this->em->persist($user);
		$this->em->flush();

		$this->mailSender->sendActivationEmail($user->getEmail(), $user->getName());

		return $user;
	}

	/**
	 * @throws \Exception
	 */
	private function validateInputs(array $data): void {
		if ($data['password']!==$data['password2']) {
			throw new \Exception('Passwords do not match');
		}

		$existingUser = $this->em->getRepository(User::class)->findOneBy(['email' => $data['email']]);
		if ($existingUser) {
			throw new \Exception('User with this email already exists');
		}
	}

}
