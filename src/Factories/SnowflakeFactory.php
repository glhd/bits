<?php

namespace Glhd\Bits\Factories;

use Carbon\CarbonInterface;
use Glhd\Bits\Bits;
use Glhd\Bits\Config\SnowflakesConfig;
use Glhd\Bits\Contracts\MakesSnowflakes;
use Glhd\Bits\Contracts\ResolvesSequences;
use Glhd\Bits\Snowflake;
use InvalidArgumentException;

/** @property SnowflakesConfig $config */
class SnowflakeFactory extends BitsFactory implements MakesSnowflakes
{
	public function __construct(
		CarbonInterface $epoch,
		public readonly int $datacenter_id,
		public readonly int $worker_id,
		SnowflakesConfig $config,
		ResolvesSequences $sequence,
	) {
		parent::__construct(
			epoch: $epoch,
			config: $config,
			sequence: $sequence,
		);
	}
	
	public function make(): Snowflake
	{
		[$timestamp, $sequence] = $this->waitForValidTimestampAndSequence();
		
		return new Snowflake($timestamp, $this->datacenter_id, $this->worker_id, $sequence, $this->epoch, $this->config);
	}
	
	public function makeFromTimestamp(CarbonInterface $timestamp): Snowflake
	{
		$timestamp = $this->diffFromEpoch($timestamp);
		$sequence = $this->sequence->next($timestamp);
		
		if ($sequence > $this->config->maxSequence()) {
			throw new InvalidArgumentException('Hit sequence limit for timestamp.');
		}
		
		return new Snowflake($timestamp, $this->datacenter_id, $this->worker_id, $sequence, $this->epoch, $this->config);
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
}
