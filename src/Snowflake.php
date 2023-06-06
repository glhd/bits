<?php

namespace Glhd\Bits;

use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Grammar;

class Snowflake implements Expression, Castable
{
	public static function make(): Snowflake
	{
		return app(Factory::class)->make();
	}
	
	public static function fromId(int|string $id): Snowflake
	{
		return app(Factory::class)->fromId($id);
	}
	
	public static function coerce(int|string|Snowflake $value): Snowflake
	{
		return app(Factory::class)->coerce($value);
	}
	
	public static function castUsing(array $arguments): string
	{
		return SnowflakeCast::class;
	}

	public function __construct(
		public readonly int $timestamp,
		public readonly int $datacenter_id,
		public readonly int $worker_id,
		public readonly int $sequence,
		protected Bits $bits = new Bits(),
	) {
		$this->validateConfiguration();
	}

	public function id(): int
	{
		return $this->bits->combine($this->timestamp, $this->datacenter_id, $this->worker_id, $this->sequence);
	}

	public function is(Snowflake $other): bool
	{
		return $other->id() === $this->id();
	}

	public function getValue(?Grammar $grammar = null): int
	{
		return $this->id();
	}

	public function __toString(): string
	{
		return (string) $this->id();
	}

	protected function validateConfiguration(): void
	{
		$this->bits->validateTimestamp($this->timestamp);
		$this->bits->validateDatacenterId($this->datacenter_id);
		$this->bits->validateWorkerId($this->worker_id);
		$this->bits->validateSequence($this->sequence);
	}
}
