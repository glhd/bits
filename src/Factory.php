<?php

namespace Glhd\Bits;

use Carbon\CarbonInterface;
use Glhd\Bits\Config\GenericConfiguration;
use Glhd\Bits\Config\WorkerIds;
use Glhd\Bits\Contracts\Configuration;
use Glhd\Bits\Contracts\MakesBits;
use Glhd\Bits\Contracts\ResolvesSequences;
use InvalidArgumentException;
use RuntimeException;

class Factory implements MakesBits
{
	public function __construct(
		protected CarbonInterface $epoch,
		protected WorkerIds $ids,
		protected Configuration $config,
		protected ResolvesSequences $sequence = new CacheSequenceResolver(),
	) {
		$this->validateConfiguration();
	}

	public function make(): Bits
	{
		[$timestamp, $sequence] = $this->waitForValidTimestampAndSequence();
		
		$values = $this->config->organize($this->ids, $timestamp, $sequence);

		return new Bits($this->config, ...$values);
	}

	public function makeFromTimestamp(CarbonInterface $timestamp): Bits
	{
		$timestamp = $this->diffFromEpoch($timestamp);
		$sequence = $this->sequence->next($timestamp);

		if ($sequence > $this->config->maxSequence()) {
			throw new InvalidArgumentException('Hit sequence limit for timestamp.');
		}
		
		$values = $this->config->organize($this->ids, $timestamp, $sequence);

		return new Bits($this->config, ...$values);
	}

	public function makeFromTimestampForQuery(CarbonInterface $timestamp): Bits
	{
		// FIXME: We may need to move this into an optional interface
		
		// return new Bits(
		// 	timestamp: $this->diffFromEpoch($timestamp),
		// 	datacenter_id: 0,
		// 	worker_id: 0,
		// 	sequence: 0,
		// 	config: $this->config,
		// );
		
		return $this->make();
	}

	public function fromId(int|string $id): Bits
	{
		$values = $this->config->parse((int) $id);

		return new Bits($this->config, ...$values);
	}

	public function coerce(int|string|Bits $value): Bits
	{
		if (! ($value instanceof Bits)) {
			$value = $this->fromId($value);
		}

		return $value;
	}

	/** @return array {0: int, 1: int} */
	protected function waitForValidTimestampAndSequence(): array
	{
		$timestamp = $this->diffFromEpoch(now());
		$sequence = $this->sequence->next($timestamp);

		// If we've used all available numbers in sequence, we'll sleep and try again
		if ($sequence > $this->config->maxSequence()) {
			usleep(1);

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
		
		$this->config->validate($this->ids);
	}
}
