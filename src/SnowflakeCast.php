<?php

namespace Glhd\Bits;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class SnowflakeCast implements CastsAttributes
{
	public function get(Model $model, string $key, mixed $value, array $attributes)
	{
		return Snowflake::coerce($value);
	}

	public function set(Model $model, string $key, mixed $value, array $attributes)
	{
		if ($value instanceof Snowflake) {
			$value = $value->id();
		}

		return (int) $value;
	}
}
