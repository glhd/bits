<?php

namespace Glhd\Bits\Factories;

use Carbon\CarbonInterface;
use Glhd\Bits\Bits;
use Glhd\Bits\Config\WorkerIds;
use Glhd\Bits\Contracts\Configuration;
use Glhd\Bits\Contracts\ResolvesSequences;
use InvalidArgumentException;

class GenericFactory extends BitsFactory
{
	public function __construct(
		CarbonInterface $epoch,
		public readonly WorkerIds $ids,
		Configuration $config,
		ResolvesSequences $sequence,
	) {
		parent::__construct(
			epoch: $epoch,
			config: $config,
			sequence: $sequence,
		);
	}
	
	public function make(): Bits
	{
		[$timestamp, $sequence] = $this->waitForValidTimestampAndSequence();
		
		$values = $this->config->organize($this->ids, $timestamp, $sequence);
		
		return new Bits($values, $this->config);
	}
	
	public function makeFromTimestamp(CarbonInterface $timestamp): Bits
	{
		$timestamp = $this->diffFromEpoch($timestamp);
		$sequence = $this->sequence->next($timestamp);
		
		if ($sequence > $this->config->maxSequence()) {
			throw new InvalidArgumentException('Hit sequence limit for timestamp.');
		}
		
		$values = $this->config->organize($this->ids, $timestamp, $sequence);
		
		return new Bits($values, $this->config);
	}
	
	public function fromId(int|string $id): Bits
	{
		$values = $this->config->parse((int) $id);
		
		return new Bits($values, $this->config);
	}
	
	public function coerce(int|string|Bits $value): Bits
	{
		if (! ($value instanceof Bits)) {
			$value = $this->fromId($value);
		}
		
		return $value;
	}
	
	protected function validateConfiguration(): void
	{
		parent::validateConfiguration();
		
		$this->config->validate($this->ids);
	}
}
