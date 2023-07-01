<?php

namespace Glhd\Bits\Tests;

use Carbon\CarbonInterval;
use Glhd\Bits\Support\BitsServiceProvider;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Sleep;
use Orchestra\Testbench\TestCase as Orchestra;
use RuntimeException;

if (!class_exists(Sleep::class)) {
	require_once __DIR__.'/../compat/Illuminate/Support/Sleep.php';
}

abstract class TestCase extends Orchestra
{
	protected function setUp(): void
	{
		parent::setUp();
		
		Sleep::whenFakingSleep(function(CarbonInterval $duration) {
			if (! Date::hasTestNow()) {
				throw new RuntimeException('Trying to sleep but no "test now" has been set.');
			}
			
			Date::setTestNow(now()->addMilliseconds($duration->totalMilliseconds));
		});
	}
	
	protected function getPackageProviders($app)
	{
		return [
			BitsServiceProvider::class,
		];
	}
	
	protected function getPackageAliases($app)
	{
		return [];
	}
	
	protected function getApplicationTimezone($app)
	{
		return 'America/New_York';
	}
}
