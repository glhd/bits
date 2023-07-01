<?php

use Glhd\Bits\Bits;
use Glhd\Bits\Contracts\MakesSnowflakes;
use Glhd\Bits\Snowflake;

if (! function_exists('snowflake')) { // @codeCoverageIgnore
	function snowflake(null|int|string|Bits $value = null): Snowflake|MakesSnowflakes
	{
		if (null === $value) {
			return app(MakesSnowflakes::class);
		}
		
		return app(MakesSnowflakes::class)->coerce($value);
	}
}
