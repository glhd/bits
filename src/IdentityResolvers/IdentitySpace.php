<?php

namespace Glhd\Bits\IdentityResolvers;

use Generator;
use Glhd\Bits\Config\Segment;
use Glhd\Bits\Config\WorkerIds;
use Glhd\Bits\Contracts\Configuration;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Every combination of worker/datacenter (or machine) IDs that a configuration can
 * represent, and the order in which a single process walks them looking for a free one.
 *
 * Any segment can be pinned to a fixed value, in which case it is excluded from the
 * space. Pinning the datacenter and leaving the worker open, for example, lets each
 * region assign its own datacenter ID while processes still claim their own worker ID.
 */
class IdentitySpace
{
	/** @var \Illuminate\Support\Collection<int, \Glhd\Bits\Config\Segment> */
	protected Collection $segments;
	
	/** @var array<int, int|null> */
	protected array $pins;
	
	/** @var int[] */
	protected array $claimable;
	
	protected int $bits;
	
	/**
	 * @param array<int|string, int|null> $pins Fixed values, keyed by segment label or by
	 *                                          position within the configuration's ID segments
	 */
	public function __construct(Configuration $config, array $pins = [])
	{
		$this->segments = $config->idSegments();
		
		if ($this->segments->isEmpty()) {
			throw new InvalidArgumentException('This configuration has no ID segments to claim.');
		}
		
		$this->pins = $this->normalizePins($pins);
		
		$this->claimable = $this->segments
			->keys()
			->reject(fn(int $index) => null !== $this->pins[$index])
			->values()
			->all();
		
		$this->bits = array_reduce(
			$this->claimable,
			fn(int $bits, int $index) => $bits + $this->segments[$index]->length,
			0
		);
	}
	
	/** The number of distinct identities a process could claim */
	public function size(): int
	{
		return 2 ** $this->bits;
	}
	
	public function bits(): int
	{
		return $this->bits;
	}
	
	/** True when every segment is pinned, so there is nothing to claim */
	public function isFixed(): bool
	{
		return [] === $this->claimable;
	}
	
	/** Turn an index in [0, size()) into a concrete set of IDs */
	public function at(int $index): WorkerIds
	{
		if ($index < 0 || $index >= $this->size()) {
			throw new InvalidArgumentException("Index {$index} is outside of an identity space of {$this->size()}.");
		}
		
		$ids = $this->pins;
		
		// Fill the claimable segments from the least significant bits up, so that the
		// last segment (conventionally the worker) varies fastest
		foreach (array_reverse($this->claimable) as $position) {
			$segment = $this->segments[$position];
			
			$ids[$position] = $index & $segment->maxValue();
			$index >>= $segment->length;
		}
		
		return new WorkerIds(...$ids);
	}
	
	/**
	 * Walk every index in the space exactly once, in a seed-dependent order. See
	 * LinearCongruentialGenerator for why this covers the whole space, and why two
	 * processes with different seeds are unlikely to collide more than once.
	 *
	 * @return \Generator<int, int>
	 */
	public function indexes(int $seed): Generator
	{
		if ($this->isFixed()) {
			yield 0;
			
			return;
		}
		
		$lcg = new LinearCongruentialGenerator($seed, $this->bits);
		
		for ($i = 0, $size = $this->size(); $i < $size; $i++) {
			yield $lcg->next();
		}
	}
	
	/**
	 * The same walk as indexes(), resolved to concrete IDs.
	 *
	 * @return \Generator<int, \Glhd\Bits\Config\WorkerIds>
	 */
	public function candidates(int $seed): Generator
	{
		foreach ($this->indexes($seed) as $index) {
			yield $this->at($index);
		}
	}
	
	/** A stable label for this space, used to namespace claims when no scope is given */
	public function signature(): string
	{
		return $this->segments
			->map(fn(Segment $segment, int $index) => sprintf(
				'%s:%d%s',
				$segment->label,
				$segment->length,
				null === $this->pins[$index] ? '' : '='.$this->pins[$index],
			))
			->join(',');
	}
	
	/** @return array<int, int|null> */
	protected function normalizePins(array $pins): array
	{
		$normalized = $this->segments->map(fn() => null)->all();
		
		foreach ($pins as $key => $value) {
			$position = is_int($key)
				? $key
				: $this->segments->search(fn(Segment $segment) => $segment->label === $key);
			
			if (false === $position || ! array_key_exists($position, $normalized)) {
				throw new InvalidArgumentException("This configuration has no ID segment for '{$key}'.");
			}
			
			if (null === $value) {
				continue;
			}
			
			$segment = $this->segments[$position];
			
			if ($value < 0 || $value > $segment->maxValue()) {
				throw new InvalidArgumentException(
					"Cannot pin '{$segment->label}' to {$value} (must be between 0 and {$segment->maxValue()})."
				);
			}
			
			$normalized[$position] = $value;
		}
		
		return $normalized;
	}
}
