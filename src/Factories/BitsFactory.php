<?php

namespace Glhd\Bits\Factories;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Closure;
use DateTime;
use Glhd\Bits\Config\WorkerIds;
use Glhd\Bits\Contracts\Configuration;
use Glhd\Bits\Contracts\MakesBits;
use Glhd\Bits\Contracts\ResolvesIdentities;
use Glhd\Bits\Contracts\ResolvesSequences;
use Illuminate\Support\Sleep;
use InvalidArgumentException;
use OutOfBoundsException;
use RuntimeException;

// Add support for Sleep class in older versions of Laravel
if (! class_exists(Sleep::class)) {
	require_once __DIR__.'/../../compat/Illuminate/Support/Sleep.php';
}

abstract class BitsFactory implements MakesBits
{
	protected ?Closure $now_resolver = null;
	
	protected ?WorkerIds $validated_ids = null;
	
	public function __construct(
		public readonly CarbonInterface $epoch,
		protected Configuration $config,
		protected ResolvesSequences $sequence,
		protected ResolvesIdentities $identities,
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
	
	/** The resolver that this factory asks for worker/datacenter IDs */
	public function identityResolver(): ResolvesIdentities
	{
		return $this->identities;
	}
	
	/**
	 * The IDs to stamp into the next generated ID.
	 *
	 * This is deliberately resolved on every ID rather than cached on the factory: a
	 * resolver that claims its identities at runtime needs the chance to renew that
	 * claim, or to notice it lost it and take a new one.
	 */
	public function workerIds(): WorkerIds
	{
		$ids = $this->identities->identities();
		
		// Only worth validating when the resolver hands back something new
		if ($ids !== $this->validated_ids) {
			$this->config->validate($ids);
			
			$this->validated_ids = $ids;
		}
		
		return $ids;
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
	
	/**
	 * Worker and datacenter IDs used to be constructor arguments, so they were readable
	 * as properties. They are resolved lazily now, but reading them still works.
	 */
	protected function workerIdProperty(string $name, array $properties): int
	{
		$position = $properties[$name] ?? null;
		
		if (null === $position) {
			throw new OutOfBoundsException(sprintf('Undefined property: %s::$%s', static::class, $name));
		}
		
		return $this->workerIds()[$position];
	}
}
