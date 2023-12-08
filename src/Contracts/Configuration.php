<?php

namespace Glhd\Bits\Contracts;

use Carbon\CarbonInterface;
use Glhd\Bits\Config\SegmentType;
use Glhd\Bits\Config\WorkerIds;
use Illuminate\Support\Collection;

interface Configuration
{
	public function organize(WorkerIds $ids, int $timestamp, int $sequence): array;
	
	/** @return int[] */
	public function parse(int $id): array;
	
	public function combine(int ...$values): int;
	
	public function indexOf(SegmentType $type): int|array;
	
	public function timestamp(CarbonInterface $epoch, CarbonInterface $timestamp): int;
	
	public function maxSequence(): int;
	
	public function validate(Collection|array|WorkerIds $values): void;
	
	public function unitInMicroseconds(): int;
	
	public function precision(): int;
	
	public function unit(): int;
}
