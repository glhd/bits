<?php

namespace Glhd\Bits;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class SnowflakeCast implements CastsAttributes
{
	public function get($model, string $key, $value, array $attributes)
	{
		return Snowflake::coerce($value);
	}

	public function set($model, string $key, $value, array $attributes)
	{
		if ($value instanceof Snowflake) {
			$value = $value->id();
		}

		return (int) $value;
	}
}
