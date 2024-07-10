<?php

namespace Glhd\Bits\Factories;

use Carbon\CarbonInterface;
use Glhd\Bits\Bits;
use Glhd\Bits\Config\SonyflakesConfig;
use Glhd\Bits\Contracts\MakesSonyflakes;
use Glhd\Bits\Contracts\ResolvesSequences;
use Glhd\Bits\Sonyflake;
use InvalidArgumentException;

/** @property SonyflakesConfig $config */
class SonyflakeFactory extends BitsFactory implements MakesSonyflakes
{
	public function __construct(
		CarbonInterface $epoch,
		public readonly int $machine_id,
		SonyflakesConfig $config,
		ResolvesSequences $sequence,
	) {
		parent::__construct(
			epoch: $epoch,
			config: $config,
			sequence: $sequence,
		);
	}
	
	public function make(): Sonyflake
	{
		[$timestamp, $sequence] = $this->waitForValidTimestampAndSequence();
		
		return new Sonyflake($timestamp, $sequence, $this->machine_id, $this->epoch, $this->config);
	}
	
	public function makeFromTimestamp(CarbonInterface $timestamp): Sonyflake
	{
		$timestamp = $this->diffFromEpoch($timestamp);
		$sequence = $this->sequence->next($timestamp);
		
		if ($sequence > $this->config->maxSequence()) {
			throw new InvalidArgumentException('Hit sequence limit for timestamp.');
		}
		
		return new Sonyflake($timestamp, $sequence, $this->machine_id, $this->epoch, $this->config);
	}
	
	public function firstForTimestamp(CarbonInterface $timestamp): Sonyflake
	{
		$timestamp = $this->diffFromEpoch($timestamp);
		
		return new Sonyflake($timestamp, 0, 0, $this->config);
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
}
