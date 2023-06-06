<?php

use Glhd\Bits\Factory;
use Glhd\Bits\Snowflake;

if (! function_exists('snowflake')) { // @codeCoverageIgnore
	function snowflake(null|int|string|Snowflake $value = null): Snowflake|Factory
	{
		if (null === $value) {
			return app(Factory::class);
		}
		
		return app(Factory::class)->coerce($value);
	}
}
