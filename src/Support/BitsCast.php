<?php

namespace Glhd\Bits\Support;

use Glhd\Bits\Bits;
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
		if (null === $value) {
			return null;
		}
		
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
