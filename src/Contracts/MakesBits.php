<?php

namespace Glhd\Bits\Contracts;

use Carbon\CarbonInterface;
use Glhd\Bits\Bits;
use Glhd\Bits\Config\WorkerIds;
use Illuminate\Support\Collection;

interface MakesBits
{
	public function make(): Bits;
	
	public function makeFromTimestamp(CarbonInterface $timestamp): Bits;
	
	public function fromId(int|string $id): Bits;
	
	public function coerce(int|string|Bits $value): Bits;
}
