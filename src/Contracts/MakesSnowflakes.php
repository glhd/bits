<?php

namespace Glhd\Bits\Contracts;

use Carbon\CarbonInterface;
use Glhd\Bits\Bits;
use Glhd\Bits\Snowflake;

interface MakesSnowflakes extends MakesBits
{
	public function make(): Snowflake;
	
	public function makeFromTimestamp(CarbonInterface $timestamp): Snowflake;
	
	public function fromId(int|string $id): Snowflake;
	
	public function coerce(int|string|Bits $value): Snowflake;
}
