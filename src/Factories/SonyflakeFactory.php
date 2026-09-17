<?php

namespace Glhd\Bits\Factories;

use Carbon\CarbonInterface;
use Glhd\Bits\Bits;
use Glhd\Bits\Config\SonyflakesConfig;
use Glhd\Bits\Contracts\MakesSonyflakes;
use Glhd\Bits\Contracts\ResolvesIdentities;
use Glhd\Bits\Contracts\ResolvesSequences;
use Glhd\Bits\IdentityResolvers\FixedIdentityResolver;
use Glhd\Bits\Sonyflake;
use InvalidArgumentException;

/**
 * @property SonyflakesConfig $config
 * @property-read int $machine_id
 */
class SonyflakeFactory extends BitsFactory implements MakesSonyflakes
{
	protected const ID_POSITIONS = ['machine_id' => 0];
	
	public static function withIdentities(
		CarbonInterface $epoch,
		ResolvesIdentities $identities,
		?SonyflakesConfig $config = null,
		?ResolvesSequences $sequence = null,
	): static {
		return new static($epoch, $identities, $config, $sequence);
	}
	
	/**
	 * @param \Glhd\Bits\Contracts\ResolvesIdentities|int $machine_id A resolver, or a fixed machine ID
	 */
	public function __construct(
		CarbonInterface $epoch,
		ResolvesIdentities|int $machine_id,
		?SonyflakesConfig $config = null,
		?ResolvesSequences $sequence = null,
	) {
		parent::__construct(
			epoch: $epoch,
			config: $config ?? app(SonyflakesConfig::class),
			sequence: $sequence ?? app(ResolvesSequences::class),
			identities: is_int($machine_id)
				? FixedIdentityResolver::make($machine_id)
				: $machine_id,
		);
	}
	
	public function machineId(): int
	{
		return $this->workerIds()[0];
	}
	
	public function make(): Sonyflake
	{
		[$timestamp, $sequence] = $this->waitForValidTimestampAndSequence();
		
		return new Sonyflake($timestamp, $sequence, $this->machineId(), $this->epoch, $this->config);
	}
	
	public function makeFromTimestamp(CarbonInterface $timestamp): Sonyflake
	{
		$timestamp = $this->diffFromEpoch($timestamp);
		$sequence = $this->sequence->next($timestamp);
		
		if ($sequence > $this->config->maxSequence()) {
			throw new InvalidArgumentException('Hit sequence limit for timestamp.');
		}
		
		return new Sonyflake($timestamp, $sequence, $this->machineId(), $this->epoch, $this->config);
	}
	
	public function firstForTimestamp(CarbonInterface $timestamp): Sonyflake
	{
		$timestamp = $this->diffFromEpoch($timestamp);
		
		return new Sonyflake($timestamp, 0, 0, $this->epoch, $this->config);
	}
	
	public function fromId(int|string $id): Sonyflake
	{
		[$timestamp, $sequence, $machine_id] = $this->config->parse((int) $id);
		
		return new Sonyflake($timestamp, $sequence, $machine_id, $this->epoch, $this->config);
	}
	
	public function coerce(int|string|Bits $value): Sonyflake
	{
		if (! $value instanceof Bits) {
			$value = $this->fromId($value);
		}
		
		if (! $value instanceof Sonyflake) {
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
}
