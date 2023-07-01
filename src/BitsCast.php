<?php

namespace Glhd\Bits;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class BitsCast implements CastsAttributes
{
	public function get($model, string $key, $value, array $attributes)
	{
		return Bits::coerce($value);
	}

	public function set($model, string $key, $value, array $attributes)
	{
		if ($value instanceof Bits) {
			$value = $value->id();
		}

		return (int) $value;
	}
}
