<?php declare(strict_types=1);

namespace App\UI\Form;

use Nette\Localization\Translator;

final class SlovakFormTranslator implements Translator
{
	public function translate(string|\Stringable $message, mixed ...$parameters): string
	{
		return match ((string) $message) {
			'This field is required.' => 'Toto pole je povinné.',
			'Please enter a valid email address.' => 'Zadajte platnú e-mailovú adresu.',
			'Please select a valid option.' => 'Vyberte platnú možnosť.',
			'The uploaded data exceeds the limit of %d bytes.' => 'Odoslané údaje prekročili povolenú veľkosť %d bajtov.',
			default => (string) $message,
		};
	}
}
