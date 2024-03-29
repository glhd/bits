<?php

namespace Glhd\Bits;

use Closure;
use Glhd\Bits\Contracts\Configuration;
use Glhd\Bits\Contracts\MakesBits;
use Glhd\Bits\Contracts\MakesSnowflakes;
use Glhd\Bits\Contracts\MakesSonyflakes;
use Glhd\Bits\Support\BitsCast;
use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Database\Grammar;
use Illuminate\Support\Collection;
use JsonSerializable;

// This adds support for the Expression interface in earlier versions of Laravel
if (! interface_exists(Expression::class)) {
	require_once __DIR__.'/../compat/Illuminate/Contracts/Database/Query/Expression.php';
}

class Bits implements Expression, Castable, Jsonable, JsonSerializable
{
	/** @var \Illuminate\Support\Collection<int, int> $values */
	public readonly Collection $values;
	
	protected ?int $id = null;

	/** @var callable|null */
	protected static $factory;
	
	public static function make(): static
	{
		return static::$factory
			? call_user_func(static::$factory)
			: app(MakesBits::class)->make();
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

	public static function freeze(Closure $callback = null): static
	{
		$flake = static::make();

		static::createUsing(fn() => $flake);

		if ($callback !== null) {
			try {
				$callback($flake);
			} finally {
				static::createNormally();
			}
		}

		return $flake;
	}

	public static function createUsingSequence(array $sequence, $whenMissing = null): void
	{
		$next = 0;

		$whenMissing ??= function() use (&$next) {
			$factoryCache = static::$factory;

			static::$factory = null;

			$flake = static::make();

			static::$factory = $factoryCache;

			$next++;

			return $flake;
		};

		static::createUsing(function() use (&$next, $sequence, $whenMissing) {
			if (array_key_exists($next, $sequence)) {
				return $sequence[$next++];
			}

			return $whenMissing();
		});
	}

	public static function createUsing(callable $factory = null): void
	{
		static::$factory = $factory;
	}

	public static function createNormally(): void
	{
		static::$factory = null;
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
		return $other::class === $this::class && $other->id() === $this->id();
	}
	
	public function getValue(?Grammar $grammar = null): int
	{
		return $this->id();
	}
	
	public function __toString(): string
	{
		return (string) $this->id();
	}
	
	public function toJson($options = 0): string
	{
		return json_encode($this->jsonSerialize(), $options);
	}
	
	public function jsonSerialize(): mixed
	{
		return (string) $this->id();
	}
}
