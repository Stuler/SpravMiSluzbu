<?php declare(strict_types = 1);

namespace App\Model\Security\Authenticator;

use App\Domain\Provider\Exception\ProviderBlockedException;
use App\Domain\Provider\Exception\ProviderNotActivatedException;
use App\Domain\Provider\Provider;
use App\Model\Database\EntityManagerDecorator;
use App\Model\Exception\Runtime\AuthenticationException;
use App\Model\Security\Identity;
use App\Model\Security\Passwords;
use App\Model\Utils\Emails;
use Nette\Security\Authenticator;

/**
 * Authenticates provider accounts against the `provider` table only.
 *
 * This is a standalone service used by ProviderSignPresenter; it intentionally does
 * not replace the default Nette authenticator (which handles user/admin accounts),
 * so a user and a provider may share the same email without colliding.
 */
final class ProviderAuthenticator
{

	public function __construct(
		private EntityManagerDecorator $em,
		private Passwords $passwords,
	)
	{
	}

	/**
	 * @throws AuthenticationException wrong credentials (generic)
	 * @throws ProviderBlockedException blocked account
	 * @throws ProviderNotActivatedException correct credentials but account still fresh
	 */
	public function authenticate(string $email, string $password): Identity
	{
		$normalized = Emails::normalize($email);

		$provider = $this->em->getRepository(Provider::class)->findOneBy(['email' => $normalized]);

		// Always verify the password before revealing any account state.
		if (!$provider instanceof Provider || $provider->getDateDeleted() !== null || !$this->passwords->verify($password, (string) $provider->getPasswordHash())) {
			throw new AuthenticationException('The credentials are incorrect.', Authenticator::IdentityNotFound);
		}

		if ($provider->isBlocked()) {
			throw new ProviderBlockedException('The provider account is blocked.');
		}

		if (!$provider->isActivated()) {
			throw new ProviderNotActivatedException('The provider account requires activation.');
		}

		$provider->setDateLastLogin();
		$this->em->flush();

		return $provider->toIdentity();
	}

}
