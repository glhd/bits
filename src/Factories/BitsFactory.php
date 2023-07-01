<?php

namespace Glhd\Bits\Factories;

use Carbon\CarbonInterface;
use Glhd\Bits\Contracts\Configuration;
use Glhd\Bits\Contracts\MakesBits;
use Glhd\Bits\Contracts\ResolvesSequences;
use Illuminate\Support\Sleep;
use InvalidArgumentException;
use RuntimeException;

// Add support for Sleep class in older versions of Laravel
if (!class_exists(Sleep::class)) {
	require_once __DIR__.'/../../compat/Illuminate/Support/Sleep.php';
}

abstract class BitsFactory implements MakesBits
{
	public function __construct(
		protected CarbonInterface $epoch,
		protected Configuration $config,
		protected ResolvesSequences $sequence,
	) {
		$this->validateConfiguration();
	}
	
	/** @return array {0: int, 1: int} */
	protected function waitForValidTimestampAndSequence(): array
	{
		$timestamp = $this->diffFromEpoch(now());
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
			throw new RuntimeException('Bits require 64-bit integer support.');
		}
		
		if ($this->epoch->isFuture()) {
			throw new InvalidArgumentException('Bits epoch cannot be in the future.');
		}
	}
}
