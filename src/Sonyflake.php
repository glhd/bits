<?php

namespace Glhd\Bits;

use Glhd\Bits\Contracts\MakesSonyflakes;
use Glhd\Bits\Presets\Sonyflakes;

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
		?Sonyflakes $config = null,
	) {
		parent::__construct(
			values: [$this->timestamp, $this->sequence, $this->machine_id],
			config: $config ?? app(Sonyflakes::class),
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
