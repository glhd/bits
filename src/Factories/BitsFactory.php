<?php

namespace Glhd\Bits\Factories;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Closure;
use DateTime;
use Glhd\Bits\Contracts\Configuration;
use Glhd\Bits\Contracts\MakesBits;
use Glhd\Bits\Contracts\ResolvesSequences;
use Illuminate\Support\Sleep;
use InvalidArgumentException;
use RuntimeException;

// Add support for Sleep class in older versions of Laravel
if (! class_exists(Sleep::class)) {
	require_once __DIR__.'/../../compat/Illuminate/Support/Sleep.php';
}

abstract class BitsFactory implements MakesBits
{
	protected ?Closure $now_resolver = null;
	
	public function __construct(
		public readonly CarbonInterface $epoch,
		protected Configuration $config,
		protected ResolvesSequences $sequence,
	) {
		$this->validateConfiguration();
	}
	
	public function setTestNow(CarbonInterface|Closure|null $now_resolver = null): static
	{
		if ($now_resolver instanceof CarbonInterface) {
			$test_now = $now_resolver;
			$now_resolver = fn() => $test_now->avoidMutation();
		}
		
		$this->now_resolver = $now_resolver;
		
		return $this;
	}
	
	protected function now(): CarbonInterface
	{
		return $this->now_resolver
			? call_user_func($this->now_resolver)
			: new CarbonImmutable(new DateTime());
	}
	
	/** @return array {0: int, 1: int} */
	protected function waitForValidTimestampAndSequence(): array
	{
		$timestamp = $this->diffFromEpoch($this->now());
		$sequence = $this->sequence->next($timestamp);
		
		// If we've used all available numbers in sequence, we'll sleep and try again
		if ($sequence > $this->config->maxSequence()) {
			Sleep::usleep($this->config->unitInMicroseconds());
			
			return $this->waitForValidTimestampAndSequence();
		}
		
		return [$timestamp, $sequence];
	}
	
	protected function diffFromEpoch(CarbonInterface $timestamp): int
	{
		return $this->config->timestamp($this->epoch, $timestamp);
	}
	
	protected function validateConfiguration(): void
	{
		if (PHP_INT_SIZE < 8) {
			throw new RuntimeException('Bits requires 64-bit integer support.');
		}
		
		if ($this->epoch->isFuture()) {
			throw new InvalidArgumentException(sprintf(
				'Trying to use "%s" as epoch, but it is currently "%s" (Bits epoch cannot be in the future).',
				$this->epoch->toDateTimeString(),
				$this->epoch->nowWithSameTz()->toDateTimeString(),
			));
		}
	}
}
