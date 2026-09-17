<?php

namespace Glhd\Bits\Contracts;

use Carbon\CarbonInterface;
use DateTimeInterface;
use Glhd\Bits\Config\SegmentType;
use Glhd\Bits\Config\WorkerIds;
use Illuminate\Support\Collection;

interface Configuration
{
	public function organize(WorkerIds $ids, int $timestamp, int $sequence): array;
	
	/** @return int[] */
	public function parse(int $id): array;
	
	public function combine(int ...$values): int;
	
	/** @return int[] */
	public function positionOf(SegmentType $type): array;
	
	/**
	 * The ID segments of this configuration, in the order they appear. These are the
	 * segments that a ResolvesIdentities implementation provides values for.
	 *
	 * @return \Illuminate\Support\Collection<int, \Glhd\Bits\Config\Segment>
	 */
	public function idSegments(): Collection;
	
	public function timestamp(CarbonInterface $epoch, CarbonInterface $timestamp): int;
	
	public function timestampToDateTime(DateTimeInterface $epoch, int $timestamp): DateTimeInterface;
	
	public function maxSequence(): int;
	
	public function validate(Collection|array|WorkerIds $values): void;
	
	public function unitInMicroseconds(): int;
}
