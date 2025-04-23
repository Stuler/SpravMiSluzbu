<?php

namespace App;

readonly class Settings
{

	public function __construct(
		public bool   $debugMode,
		public string $appDir,
		public string $wwwDir,
		public string $testPassword,
		public string $testUrl
	)
	{
	}
}
