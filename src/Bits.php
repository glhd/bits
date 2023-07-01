<?php

namespace Glhd\Bits;

use Glhd\Bits\Contracts\Configuration;
use Glhd\Bits\Contracts\MakesBits;
use Glhd\Bits\Contracts\MakesSnowflakes;
use Glhd\Bits\Contracts\MakesSonyflakes;
use Glhd\Bits\Support\BitsCast;
use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Grammar;
use Illuminate\Support\Collection;

// This adds support for the Expression interface in earlier versions of Laravel
if (! interface_exists(Expression::class)) {
	require_once __DIR__.'/../compat/Illuminate/Contracts/Database/Query/Expression.php';
}

class Bits implements Expression, Castable
{
	/** @var \Illuminate\Support\Collection<int, int> $values */
	protected Collection $values;
	
	protected ?int $id = null;
	
	public static function make(): static
	{
		return app(MakesBits::class)->make();
	}
	
	public static function fromId(int|string $id): static
	{
		return app(MakesBits::class)->fromId($id);
	}
	
	public static function coerce(int|string|Bits $value): static
	{
		return app(MakesBits::class)->coerce($value);
	}
	
	public static function castUsing(array $arguments): BitsCast
	{
		return match (reset($arguments)) {
			'sonyflake', 'sonyflakes' => new BitsCast(app(MakesSonyflakes::class)),
			default => new BitsCast(app(MakesSnowflakes::class)),
		};
	}
	
	public function __construct(
		array $values,
		protected Configuration $config
	) {
		$this->config->validate($values);
		
		$this->values = new Collection($values);
	}
	
	public function id(): int
	{
		return $this->id ??= $this->config->combine(...$this->values);
	}
	
	public function is(Bits $other): bool
	{
		return ($other instanceof static) && $other->id() === $this->id();
	}
	
	public function getValue(?Grammar $grammar = null): int
	{
		return $this->id();
	}
	
	public function __toString(): string
	{
		return (string) $this->id();
	}
}
