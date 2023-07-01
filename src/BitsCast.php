<?php

namespace Glhd\Bits;

use Glhd\Bits\Contracts\MakesBits;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class BitsCast implements CastsAttributes
{
	public function __construct(
		protected MakesBits $factory
	) {
	}
	
	public function get($model, string $key, $value, array $attributes)
	{
		return $this->factory->coerce($value);
	}

	public function set($model, string $key, $value, array $attributes)
	{
		if ($value instanceof Bits) {
			$value = $value->id();
		}

		return (int) $value;
	}
}
