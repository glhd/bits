<?php

namespace Glhd\Bits\Factories;

use Carbon\CarbonInterface;
use Glhd\Bits\Bits;
use Glhd\Bits\Config\WorkerIds;
use Glhd\Bits\Contracts\Configuration;
use Glhd\Bits\Contracts\ResolvesIdentities;
use Glhd\Bits\Contracts\ResolvesSequences;
use Glhd\Bits\IdentityResolvers\FixedIdentityResolver;
use InvalidArgumentException;
use OutOfBoundsException;

/** @property-read WorkerIds $ids */
class GenericFactory extends BitsFactory
{
	public static function withIdentities(
		CarbonInterface $epoch,
		ResolvesIdentities $identities,
		?Configuration $config = null,
		?ResolvesSequences $sequence = null,
	): static {
		return new static($epoch, $identities, $config, $sequence);
	}
	
	/**
	 * @param \Glhd\Bits\Contracts\ResolvesIdentities|\Glhd\Bits\Config\WorkerIds $ids A resolver, or a fixed set of IDs
	 */
	public function __construct(
		CarbonInterface $epoch,
		ResolvesIdentities|WorkerIds $ids,
		?Configuration $config = null,
		?ResolvesSequences $sequence = null,
	) {
		parent::__construct(
			epoch: $epoch,
			config: $config ?? app(Configuration::class),
			sequence: $sequence ?? app(ResolvesSequences::class),
			identities: $ids instanceof WorkerIds
				? new FixedIdentityResolver($ids)
				: $ids,
		);
	}
	
	public function ids(): WorkerIds
	{
		return $this->workerIds();
	}
	
	public function make(): Bits
	{
		[$timestamp, $sequence] = $this->waitForValidTimestampAndSequence();
		
		$values = $this->config->organize($this->workerIds(), $timestamp, $sequence);
		
		return new Bits($values, $this->config, $this->epoch);
	}
	
	public function makeFromTimestamp(CarbonInterface $timestamp): Bits
	{
		$timestamp = $this->diffFromEpoch($timestamp);
		$sequence = $this->sequence->next($timestamp);
		
		if ($sequence > $this->config->maxSequence()) {
			throw new InvalidArgumentException('Hit sequence limit for timestamp.');
		}
		
		$values = $this->config->organize($this->workerIds(), $timestamp, $sequence);
		
		return new Bits($values, $this->config, $this->epoch);
	}
	
	public function firstForTimestamp(CarbonInterface $timestamp): Bits
	{
		$timestamp = $this->diffFromEpoch($timestamp);
		
		$values = $this->config->organize(
			ids: new WorkerIds(...array_fill(0, $this->config->idSegments()->count(), 0)),
			timestamp: $timestamp,
			sequence: 0
		);
		
		return new Bits($values, $this->config, $this->epoch);
	}
	
	public function fromId(int|string $id): Bits
	{
		$values = $this->config->parse((int) $id);
		
		return new Bits($values, $this->config, $this->epoch);
	}
	
	public function coerce(int|string|Bits $value): Bits
	{
		if (! ($value instanceof Bits)) {
			$value = $this->fromId($value);
		}
		
		return $value;
	}
	
	public function __get(string $name): WorkerIds
	{
		if ('ids' !== $name) {
			throw new OutOfBoundsException(sprintf('Undefined property: %s::$%s', static::class, $name));
		}
		
		return $this->workerIds();
	}
	
	public function __isset(string $name): bool
	{
		return 'ids' === $name;
	}
}
