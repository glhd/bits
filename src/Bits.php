<?php

namespace Glhd\Bits;

use Glhd\Bits\Config\GenericConfiguration;
use Glhd\Bits\Config\WorkerIds;
use Glhd\Bits\Contracts\Configuration;
use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Grammar;
use Illuminate\Support\Collection;

// This adds support for the Expression interface in earlier versions of Laravel
if (! interface_exists('\\Illuminate\\Contracts\\Database\\Query\\Expression')) {
	require_once __DIR__.'/../compat/Illuminate/Contracts/Database/Query/Expression.php';
}

class Bits implements Expression, Castable
{
	/** @var \Illuminate\Support\Collection<int, int> $values */
	protected Collection $values;
	
	protected ?int $id = null;
	
	public static function make(): Bits
	{
		return app(Factory::class)->make();
	}
	
	public static function fromId(int|string $id): Bits
	{
		return app(Factory::class)->fromId($id);
	}
	
	public static function coerce(int|string|Bits $value): Bits
	{
		return app(Factory::class)->coerce($value);
	}
	
	public static function castUsing(array $arguments): string
	{
		// FIXME: Accept an optional preset
		return BitsCast::class;
	}

	public function __construct(
		protected Configuration $config,
		int ...$values,
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
		return ($other instanceof static::class) && $other->id() === $this->id();
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
