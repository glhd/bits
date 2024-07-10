<?php

namespace Glhd\Bits\Contracts;

use Carbon\CarbonInterface;
use Glhd\Bits\Bits;

interface MakesBits
{
	public function make(): Bits;
	
	public function makeFromTimestamp(CarbonInterface $timestamp): Bits;
	
	public function firstForTimestamp(CarbonInterface $timestamp): Bits;
	
	public function fromId(int|string $id): Bits;
	
	public function coerce(int|string|Bits $value): Bits;
}
