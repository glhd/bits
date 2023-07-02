<?php

namespace Illuminate\Support;

use Carbon\CarbonInterval;
use PHPUnit\Framework\Assert as PHPUnit;

class Sleep
{
	public static array $fakeSleepCallbacks = [];
	
	public CarbonInterval $duration;
	
	protected static bool $fake = false;
	
	protected static array $sequence = [];
	
	protected bool $shouldSleep = true;
	
	public static function usleep($duration): Sleep
	{
		$sleep = new self();
		
		$sleep->duration = CarbonInterval::microsecond($duration);
		
		return $sleep;
	}
	
	public function __destruct()
	{
		if (! $this->shouldSleep) {
			return;
		}
		
		if (static::$fake) {
			static::$sequence[] = $this->duration;
			
			foreach (static::$fakeSleepCallbacks as $callback) {
				$callback($this->duration);
			}
			
			return;
		}
		
		$microseconds = (int) $this->duration->totalMicroseconds;
		
		if ($microseconds > 0) {
			usleep($microseconds);
		}
	}
	
	public static function fake($value = true)
	{
		static::$fake = $value;
		
		static::$sequence = [];
		static::$fakeSleepCallbacks = [];
	}
	
	public static function assertSlept($expected, $times = 1)
	{
		$count = collect(static::$sequence)->filter($expected)->count();
		
		PHPUnit::assertSame(
			$times,
			$count,
			"The expected sleep was found [{$count}] times instead of [{$times}]."
		);
	}
	
	public static function assertNeverSlept()
	{
		PHPUnit::assertCount(0, static::$sequence, 'Expected to have never slept.');
	}
	
	public static function whenFakingSleep($callback)
	{
		static::$fakeSleepCallbacks[] = $callback;
	}
}
