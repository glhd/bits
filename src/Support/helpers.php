<?php

use Glhd\Bits\Bits;
use Glhd\Bits\Contracts\MakesSnowflakes;
use Glhd\Bits\Contracts\MakesSonyflakes;
use Glhd\Bits\Snowflake;
use Glhd\Bits\Sonyflake;

if (! function_exists('snowflake')) {
	function snowflake(null|int|string|Bits $value = null): Snowflake|MakesSnowflakes
	{
		if (null === $value) {
			return app(MakesSnowflakes::class);
		}
		
		return app(MakesSnowflakes::class)->coerce($value);
	}
}

if (! function_exists('snowflake_id')) {
	function snowflake_id(): int
	{
		return app(MakesSnowflakes::class)->make()->id();
	}
}

if (! function_exists('sonyflake')) {
	function sonyflake(null|int|string|Bits $value = null): Sonyflake|MakesSonyflakes
	{
		if (null === $value) {
			return app(MakesSonyflakes::class);
		}
		
		return app(MakesSonyflakes::class)->coerce($value);
	}
}
