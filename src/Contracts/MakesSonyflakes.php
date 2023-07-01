<?php

namespace Glhd\Bits\Contracts;

use Carbon\CarbonInterface;
use Glhd\Bits\Bits;
use Glhd\Bits\Sonyflake;

interface MakesSonyflakes extends MakesBits
{
	public function make(): Sonyflake;
	
	public function makeFromTimestamp(CarbonInterface $timestamp): Sonyflake;
	
	public function fromId(int|string $id): Sonyflake;
	
	public function coerce(int|string|Bits $value): Sonyflake;
}
