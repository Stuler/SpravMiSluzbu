<?php

namespace App;

class Settings
{

	public function __construct(
		public readonly bool   $debugMode,
		public readonly string $appDir,
		public readonly string $wwwDir,
		public readonly string $testPassword,
		public readonly string $testUrl
	)
	{
	}
}
