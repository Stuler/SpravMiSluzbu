<?php declare(strict_types = 1);

use App\Domain\Provider\DTO\ProviderRegistrationDataFactory;
use Tester\Assert;

require_once __DIR__ . '/../../../../bootstrap.php';

/**
 * @return array<string, mixed>
 */
function validPayload(array $override = []): array
{
	return array_merge([
		'serviceCategories' => [['value' => 5, 'label' => 'Maľovanie']],
		'coveredRegions' => [['value' => 2, 'label' => 'Bratislavský kraj']],
		'firstName' => 'Janko',
		'lastName' => 'Hraško',
		'email' => 'Janko@Example.com',
		'password' => 'secret123',
		'confirmPassword' => 'secret123',
		'phone' => '+421900000000',
		'companyName' => 'Hraško s.r.o.',
		'ico' => '12345678',
		'street' => 'Hlavná',
		'streetNumber' => '12',
		'cityId' => '7',
		'city' => 'Bratislava',
		'zip' => '81101',
		'usePersonalAsContact' => true,
		'contactFirstName' => '',
		'contactLastName' => '',
	], $override);
}

// A fully populated payload is valid.
test(function (): void {
	$dto = ProviderRegistrationDataFactory::fromArray(validPayload());
	Assert::true($dto->isValid());
});

// IČO is optional.
test(function (): void {
	$dto = ProviderRegistrationDataFactory::fromArray(validPayload(['ico' => '']));
	Assert::true($dto->isValid());
});

test(function (): void {
	$dto = ProviderRegistrationDataFactory::fromArray(validPayload(['phone' => '02 1234 5678']));
	Assert::true($dto->isValid());
	Assert::same('+421212345678', $dto->phone);
	Assert::false(ProviderRegistrationDataFactory::fromArray(validPayload(['phone' => 'invalid']))->isValid());
});

// Password shorter than 8 characters is invalid.
test(function (): void {
	$dto = ProviderRegistrationDataFactory::fromArray(validPayload([
		'password' => 'short1',
		'confirmPassword' => 'short1',
	]));
	Assert::false($dto->isValid());
});

// Mismatched passwords are invalid.
test(function (): void {
	$dto = ProviderRegistrationDataFactory::fromArray(validPayload(['confirmPassword' => 'different1']));
	Assert::false($dto->isValid());
});

// Missing required fields are invalid.
test(function (): void {
	foreach (['firstName', 'lastName', 'phone', 'companyName', 'street', 'streetNumber', 'zip', 'email'] as $field) {
		$dto = ProviderRegistrationDataFactory::fromArray(validPayload([$field => '']));
		Assert::false($dto->isValid(), "Expected invalid when $field is empty");
	}
});

// Missing category or region is invalid.
test(function (): void {
	Assert::false(ProviderRegistrationDataFactory::fromArray(validPayload(['serviceCategories' => []]))->isValid());
	Assert::false(ProviderRegistrationDataFactory::fromArray(validPayload(['coveredRegions' => []]))->isValid());
});

// Invalid email is rejected.
test(function (): void {
	Assert::false(ProviderRegistrationDataFactory::fromArray(validPayload(['email' => 'not-an-email']))->isValid());
});

// toProviderData maps ids and combines street + street number.
test(function (): void {
	$dto = ProviderRegistrationDataFactory::fromArray(validPayload());
	$data = $dto->toProviderData();

	Assert::same([5], $data['serviceCategory']);
	Assert::same([2], $data['region']);
	Assert::same('Hlavná 12', $data['streetNo']);
	Assert::same(7, $data['city']);
	Assert::same('Janko@Example.com', $data['email']);
});

// Contact person falls back to personal data when usePersonalAsContact is true.
test(function (): void {
	$dto = ProviderRegistrationDataFactory::fromArray(validPayload([
		'usePersonalAsContact' => true,
		'contactFirstName' => 'Ignored',
		'contactLastName' => 'Ignored',
	]));
	Assert::same('Janko', $dto->getContactFirstName());
	Assert::same('Hraško', $dto->getContactLastName());
});

// Separate contact person is used when usePersonalAsContact is false.
test(function (): void {
	$dto = ProviderRegistrationDataFactory::fromArray(validPayload([
		'usePersonalAsContact' => false,
		'contactFirstName' => 'Fero',
		'contactLastName' => 'Mrkva',
	]));
	Assert::same('Fero', $dto->getContactFirstName());
	Assert::same('Mrkva', $dto->getContactLastName());
});
