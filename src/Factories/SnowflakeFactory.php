<?php

namespace Glhd\Bits\Factories;

use Carbon\CarbonInterface;
use Glhd\Bits\Bits;
use Glhd\Bits\CacheSequenceResolver;
use Glhd\Bits\Config\WorkerIds;
use Glhd\Bits\Contracts\Configuration;
use Glhd\Bits\Contracts\MakesBits;
use Glhd\Bits\Contracts\MakesSnowflakes;
use Glhd\Bits\Contracts\ResolvesSequences;
use Glhd\Bits\Presets\Snowflakes;
use Glhd\Bits\Snowflake;
use Illuminate\Support\Facades\Date;
use InvalidArgumentException;
use RuntimeException;

/** @property Snowflakes $config */
class SnowflakeFactory extends BitsFactory implements MakesSnowflakes
{
	public function __construct(
		CarbonInterface $epoch,
		protected int $datacenter_id,
		protected int $worker_id,
		Snowflakes $config,
		ResolvesSequences $sequence,
	) {
		parent::__construct(
			epoch: $epoch,
			ids: new WorkerIds($datacenter_id, $worker_id),
			config: $config,
			sequence: $sequence,
		);
	}
	
	public function make(): Snowflake
	{
		[$timestamp, $sequence] = $this->waitForValidTimestampAndSequence();
		
		return new Snowflake($timestamp, $this->datacenter_id, $this->worker_id, $sequence, $this->config);
	}
	
	public function makeFromTimestamp(CarbonInterface $timestamp): Snowflake
	{
		$timestamp = $this->diffFromEpoch($timestamp);
		$sequence = $this->sequence->next($timestamp);
		
		if ($sequence > $this->config->maxSequence()) {
			throw new InvalidArgumentException('Hit sequence limit for timestamp.');
		}
		
		return new Snowflake($timestamp, $this->datacenter_id, $this->worker_id, $sequence, $this->config);
	}
	
	public function fromId(int|string $id): Snowflake
	{
		[$_, $timestamp, $datacenter_id, $worker_id, $sequence] = $this->config->parse((int) $id);
		
		return new Snowflake($timestamp, $datacenter_id, $worker_id, $sequence, $this->config);
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
