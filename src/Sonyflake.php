<?php

namespace Glhd\Bits;

use Carbon\CarbonInterface;
use Glhd\Bits\Config\SonyflakesConfig;
use Glhd\Bits\Contracts\MakesSonyflakes;

class Sonyflake extends Bits
{
	public static function make(): static
	{
		return app(MakesSonyflakes::class)->make();
	}
	
	public static function fromId(int|string $id): static
	{
		return app(MakesSonyflakes::class)->fromId($id);
	}
	
	public static function coerce(int|string|Bits $value): static
	{
		return app(MakesSonyflakes::class)->coerce($value);
	}
	
	public function __construct(
		public readonly int $timestamp,
		public readonly int $sequence,
		public readonly int $machine_id,
		CarbonInterface $epoch,
		?SonyflakesConfig $config = null,
	) {
		parent::__construct(
			values: [$this->timestamp, $this->sequence, $this->machine_id],
			config: $config ?? app(SonyflakesConfig::class),
			epoch: $epoch,
		);
	}

	public function id(): int
	{
		return $this->id ??= $this->config->combine(
			$this->timestamp,
			$this->sequence,
			$this->machine_id
		);
	}
}
