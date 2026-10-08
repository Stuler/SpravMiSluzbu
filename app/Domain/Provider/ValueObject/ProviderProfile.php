<?php declare(strict_types=1);

namespace App\Domain\Provider\ValueObject;

use App\Domain\City\City;
use App\Model\Utils\PhoneNumbers;

final readonly class ProviderProfile
{
    public string $companyName;
    public string $contactName;
    public string $surname;
    public string $title;
    public string $phone;
    public string $ico;
    public string $dic;
    public string $streetNo;
    public string $zipCode;

    public function __construct(string $companyName, string $contactName, string $surname, string $title,
        string $phone, string $ico, string $dic, string $streetNo, public City $city, string $zipCode)
    {
        $this->companyName = self::text($companyName, 255, true);
        $this->contactName = self::text($contactName, 64, true);
        $this->surname = self::text($surname, 64, true);
        $this->title = self::text($title, 32);
        $phone = self::text($phone, 100, true);
        if (!PhoneNumbers::isValid($phone) || !$city->getUse()) {
            throw new \InvalidArgumentException('Invalid provider phone or city.');
        }
        $this->phone = PhoneNumbers::normalize($phone);
        $this->ico = self::text($ico, 100);
        $this->dic = self::text($dic, 100);
        $this->streetNo = self::text($streetNo, 100, true);
        $this->zipCode = self::text($zipCode, 100, true);
    }

    private static function text(string $value, int $limit, bool $required = false): string
    {
        $value = trim($value);
        if (($required && $value === '') || mb_strlen($value) > $limit) {
            throw new \InvalidArgumentException('Invalid provider profile field.');
        }
        return $value;
    }
}
