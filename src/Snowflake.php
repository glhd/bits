<?php

namespace Glhd\Bits;

use Glhd\Bits\Config\SnowflakesConfig;
use Glhd\Bits\Contracts\MakesSnowflakes;

class Snowflake extends Bits
{
	public static function make(): static
	{
		return app(MakesSnowflakes::class)->make();
	}
	
	public static function fromId(int|string $id): static
	{
		return app(MakesSnowflakes::class)->fromId($id);
	}
	
	public static function coerce(int|string|Bits $value): static
	{
		return app(MakesSnowflakes::class)->coerce($value);
	}
	
	public function __construct(
		public readonly int $timestamp,
		public readonly int $datacenter_id,
		public readonly int $worker_id,
		public readonly int $sequence,
		?SnowflakesConfig $config = null,
	) {
		parent::__construct(
			values: [0, $this->timestamp, $this->datacenter_id, $this->worker_id, $this->sequence],
			config: $config ?? app(SnowflakesConfig::class),
		);
	}

	public function id(): int
	{
		return $this->id ??= $this->config->combine(
			0,
			$this->timestamp,
			$this->datacenter_id,
			$this->worker_id,
			$this->sequence
		);
	}
}
