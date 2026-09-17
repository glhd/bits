<?php

namespace Glhd\Bits\Factories;

use Carbon\CarbonInterface;
use Glhd\Bits\Bits;
use Glhd\Bits\Config\SnowflakesConfig;
use Glhd\Bits\Config\WorkerIds;
use Glhd\Bits\Contracts\MakesSnowflakes;
use Glhd\Bits\Contracts\ResolvesIdentities;
use Glhd\Bits\Contracts\ResolvesSequences;
use Glhd\Bits\IdentityResolvers\FixedIdentityResolver;
use Glhd\Bits\Snowflake;
use InvalidArgumentException;

/**
 * @property SnowflakesConfig $config
 * @property-read int $datacenter_id
 * @property-read int $worker_id
 */
class SnowflakeFactory extends BitsFactory implements MakesSnowflakes
{
	protected const ID_POSITIONS = ['datacenter_id' => 0, 'worker_id' => 1];
	
	public static function withIdentities(
		CarbonInterface $epoch,
		ResolvesIdentities $identities,
		?SnowflakesConfig $config = null,
		?ResolvesSequences $sequence = null,
	): static {
		return new static($epoch, $identities, null, $config, $sequence);
	}
	
	/**
	 * @param \Glhd\Bits\Contracts\ResolvesIdentities|int $datacenter_id A resolver, or a fixed
	 *                                                                  datacenter ID paired with $worker_id
	 */
	public function __construct(
		CarbonInterface $epoch,
		ResolvesIdentities|int $datacenter_id,
		?int $worker_id = null,
		?SnowflakesConfig $config = null,
		?ResolvesSequences $sequence = null,
	) {
		if (is_int($datacenter_id) && null === $worker_id) {
			throw new InvalidArgumentException(
				'A fixed datacenter ID must be paired with a worker ID. Pass a ResolvesIdentities '
				.'implementation instead to have both claimed at runtime.'
			);
		}
		
		parent::__construct(
			epoch: $epoch,
			config: $config ?? app(SnowflakesConfig::class),
			sequence: $sequence ?? app(ResolvesSequences::class),
			identities: is_int($datacenter_id)
				? FixedIdentityResolver::make($datacenter_id, $worker_id)
				: $datacenter_id,
		);
	}
	
	public function datacenterId(): int
	{
		return $this->workerIds()[0];
	}
	
	public function workerId(): int
	{
		return $this->workerIds()[1];
	}
	
	public function make(): Snowflake
	{
		[$timestamp, $sequence] = $this->waitForValidTimestampAndSequence();
		
		return $this->snowflake($timestamp, $sequence, $this->workerIds());
	}
	
	public function makeFromTimestamp(CarbonInterface $timestamp): Snowflake
	{
		$timestamp = $this->diffFromEpoch($timestamp);
		$sequence = $this->sequence->next($timestamp);
		
		if ($sequence > $this->config->maxSequence()) {
			throw new InvalidArgumentException('Hit sequence limit for timestamp.');
		}
		
		return $this->snowflake($timestamp, $sequence, $this->workerIds());
	}
	
	public function firstForTimestamp(CarbonInterface $timestamp): Snowflake
	{
		$timestamp = $this->diffFromEpoch($timestamp);
		
		return new Snowflake($timestamp, 0, 0, 0, $this->epoch, $this->config);
	}
	
	public function fromId(int|string $id): Snowflake
	{
		[$_, $timestamp, $datacenter_id, $worker_id, $sequence] = $this->config->parse((int) $id);
		
		return new Snowflake($timestamp, $datacenter_id, $worker_id, $sequence, $this->epoch, $this->config);
	}
	
	public function coerce(int|string|Bits $value): Snowflake
	{
		if (! $value instanceof Bits) {
			$value = $this->fromId($value);
		}
		
		if (! $value instanceof Snowflake) {
			$value = $this->fromId($value->id());
		}
		
		return $value;
	}
	
	public function __get(string $name): int
	{
		return $this->workerIdProperty($name, static::ID_POSITIONS);
	}
	
	public function __isset(string $name): bool
	{
		return array_key_exists($name, static::ID_POSITIONS);
	}
	
	protected function snowflake(int $timestamp, int $sequence, WorkerIds $ids): Snowflake
	{
		return new Snowflake($timestamp, $ids[0], $ids[1], $sequence, $this->epoch, $this->config);
	}
}
