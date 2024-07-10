<?php

namespace Glhd\Bits\Config;

use BadMethodCallException;
use Carbon\CarbonInterface;
use DateTimeImmutable;
use DateTimeInterface;
use Glhd\Bits\Contracts\Configuration;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/** @property Collection<int, \Glhd\Bits\Config\Segment> $segments */
class GenericConfig implements Configuration
{
	protected ?Segment $timestamp_segment = null;
	
	protected ?Segment $sequence_segment = null;
	
	public function __construct(
		protected int $precision,
		protected int $unit,
		protected Collection $segments,
	) {
		$this->setTimestampAndSequenceSegments();
		$this->setPositionsAndOffsets();
		
		$this->validateConfiguration();
	}
	
	public function organize(WorkerIds $ids, int $timestamp, int $sequence): array
	{
		$provided_count = count($ids->ids) + 2;
		$segment_count = $this->segments->count();
		
		if ($provided_count !== $segment_count) {
			throw new InvalidArgumentException("Expected {$segment_count} segments but received {$provided_count}.");
		}
		
		$ids = $ids->ids;
		
		return $this->segments
			->map(function(Segment $segment) use ($timestamp, $sequence, &$ids) {
				return match ($segment->type) {
					SegmentType::Id => array_shift($ids),
					SegmentType::Timestamp => $timestamp,
					SegmentType::Sequence => $sequence,
				};
			})
			->toArray();
	}
	
	public function parse(int $id): array
	{
		return $this->segments
			->map(fn(Segment $segment) => ($id & $segment->mask()) >> $segment->shift())
			->toArray();
	}
	
	public function combine(int ...$values): int
	{
		return Collection::make($values)
			->reduce(function(int $combined, int $value, int $index) {
				$segment = $this->getSegment($index);
				return $combined | (($value << $segment->shift()) & $segment->mask());
			}, 0);
	}
	
	public function positionOf(SegmentType $type): array
	{
		$matches = $this->segments
			->filter(fn(Segment $segment) => $segment->type === $type)
			->map(fn(Segment $segment) => $segment->position());
		
		return $matches->values()->toArray();
	}
	
	public function timestamp(CarbonInterface $epoch, CarbonInterface $timestamp): int
	{
		return (int) round(($this->getPreciseTimestamp($timestamp) - $this->getPreciseTimestamp($epoch)) / $this->unit);
	}
	
	public function timestampToDateTime(DateTimeInterface $epoch, int $timestamp): DateTimeImmutable
	{
		// The multiplier is the inverse of the precision. For example, if the precision is 6 (i.e. 6 digits
		// of decimal points after the second [aka microseconds]), then our multiplier is 1. On the other hand,
		// if the precision is 3 (aka milliseconds), our multiplier needs to be 1,000, to convert milliseconds
		// to microseconds (eg. 1,000ms = 1,000,000us).
		$multiplier = pow(10, 6 - $this->precision);
		
		// First, convert the timestamp from a relative integer to a full-precision timestamp (in microseconds)
		$precise_timestamp = ((float) $epoch->format('Uu')) + ($timestamp * $this->unit * $multiplier);
		
		// We need to then convert our full-precision timestamp into a format that PHP can parse into a precise
		// DateTime object. The format "U.u" seems to be the best way to do that, so we need to split our timestamp
		// into a unix timestamp in seconds, and exactly 6 digits of microseconds to add to that timestamp.
		$seconds = (int) floor($precise_timestamp / 1_000_000);
		$remaining_microseconds = ($precise_timestamp % 1_000_000) * 1_000_000;
		$formatted_microseconds = substr(sprintf('%06d', $remaining_microseconds), 0, 6);
		
		if ($result = DateTimeImmutable::createFromFormat('U.u', "{$seconds}.{$formatted_microseconds}", $epoch->getTimezone())) {
			return $result;
		}
		
		throw new RuntimeException('Carbon error: '.json_encode(DateTimeImmutable::getLastErrors()));
	}
	
	public function maxSequence(): int
	{
		return $this->sequence_segment->maxValue();
	}
	
	public function validate(Collection|array|WorkerIds $values): void
	{
		if ($values instanceof WorkerIds) {
			$values = $this->mapWorkerIdsToPositions($values);
		}
		
		foreach ($values as $index => $value) {
			$this->validateValueForSegment($index, $value);
		}
	}
	
	public function unitInMicroseconds(): int
	{
		return ceil(1000000 * ($this->unit / (10 ** $this->precision)));
	}
	
	protected function mapWorkerIdsToPositions(WorkerIds $values): Collection
	{
		$ids = $values->ids;
		
		return $this->segments
			->filter(fn(Segment $segment) => $segment->isId())
			->mapWithKeys(function(Segment $segment) use (&$ids) {
				return [$segment->position() => array_shift($ids)];
			});
	}
	
	protected function validateValueForSegment(int $index, $value): void
	{
		$segment = $this->getSegment($index);
		
		if (! is_int($value) || $value < 0 || $value > $segment->maxValue()) {
			$label = Str::of($segment->label)->ucfirst()->plural();
			throw new InvalidArgumentException("{$label} must be an integer between 0 and {$segment->maxValue()} (got {$value}).");
		}
	}
	
	protected function getPreciseTimestamp(CarbonInterface $timestamp): int
	{
		return $timestamp->getPreciseTimestamp($this->precision);
	}
	
	protected function getSegment(int $index): Segment
	{
		$segment = $this->segments[$index] ?? null;
		
		if (null === $segment) {
			throw new BadMethodCallException("No segment at index '{$index}'");
		}
		
		return $segment;
	}
	
	protected function validateConfiguration(): void
	{
		if ($this->precision < 0 || $this->precision > 6) {
			throw new InvalidArgumentException("Timestamp precision must be between 0 and 6 (got {$this->precision}).");
		}
		
		if ($this->unit < 0 || $this->unit > $this->maxUnit()) {
			throw new InvalidArgumentException("Timestamp unit must be between 0 and {$this->maxUnit()} when precision is set to {$this->precision} (got {$this->unit}).");
		}
		
		if ($this->segments->contains(fn($segment) => ! $segment instanceof Segment)) {
			throw new InvalidArgumentException("All segments must be of type 'Segment'");
		}
		
		$total_bits = $this->segments->sum(fn(Segment $segment) => $segment->length);
		
		if (64 !== $total_bits) {
			throw new InvalidArgumentException("The total number of bits for all segments in an ID must equal 64 (got {$total_bits}).");
		}
	}
	
	protected function maxUnit(): int
	{
		return (10 ** $this->precision);
	}
	
	protected function setTimestampAndSequenceSegments(): void
	{
		foreach ($this->segments as $segment) {
			// Make sure we have exactly one timestamp segment
			if ($segment->type === SegmentType::Timestamp) {
				if (null !== $this->timestamp_segment) {
					throw new InvalidArgumentException('You can only provide one timestamp segment to a Bits configuration.');
				}
				$this->timestamp_segment = $segment;
			}
			
			// Make sure we have exactly one sequence segment
			if ($segment->type === SegmentType::Sequence) {
				if (null !== $this->sequence_segment) {
					throw new InvalidArgumentException('You can only provide one sequence segment to a Bits configuration.');
				}
				$this->sequence_segment = $segment;
			}
		}
		
		if (null === $this->timestamp_segment) {
			throw new InvalidArgumentException('You must provide a timestamp segment to a Bits configuration.');
		}
		
		if (null === $this->sequence_segment) {
			throw new InvalidArgumentException('You must provide a sequence segment to a Bits configuration.');
		}
	}
	
	protected function setPositionsAndOffsets(): void
	{
		$shift = 0;
		
		foreach ($this->segments->reverse() as $index => $segment) {
			$segment->setPosition($index);
			$segment->setOffset($shift);
			
			$shift += $segment->length;
		}
	}
}
