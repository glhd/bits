<?php

namespace Glhd\Bits\Config;

use BadMethodCallException;

class Segment
{
	protected ?int $position = null;
	
	protected ?int $offset = null;
	
	public static function id(string $label, int $length): static
	{
		return new static($label, $length, SegmentType::Id);
	}
	
	public static function timestamp(string $label, int $length): static
	{
		return new static($label, $length, SegmentType::Timestamp);
	}
	
	public static function sequence(string $label, int $length): static
	{
		return new static($label, $length, SegmentType::Sequence);
	}
	
	public function __construct(
		public string $label,
		public int $length,
		public SegmentType $type,
	) {
	}
	
	public function setPosition(int $position): static
	{
		$this->position = $position;
		
		return $this;
	}
	
	public function setOffset(int $offset): static
	{
		$this->offset = $offset;
		
		return $this;
	}
	
	public function position(): int
	{
		if (null === $this->position) {
			throw new BadMethodCallException("The position for '{$this->label}' has not been set.");
		}
		
		return $this->position;
	}
	
	public function shift(): int
	{
		if (null === $this->offset) {
			throw new BadMethodCallException("The offset for '{$this->label}' has not been set.");
		}
		
		return $this->offset;
	}
	
	public function mask(): int
	{
		return $this->maxValue() << $this->shift();
	}
	
	public function maxValue(): int
	{
		return (2 ** $this->length) - 1;
	}
	
	public function isId(): bool
	{
		return $this->type === SegmentType::Id;
	}
}
