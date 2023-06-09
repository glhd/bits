<?php

namespace Glhd\Bits\Config;

use ArrayAccess;
use LogicException;

class WorkerIds implements ArrayAccess
{
	/** @var int[] */
	public readonly array $ids;
	
	public function __construct(int ...$ids)
	{
		$this->ids = $ids;
	}
	
	public function first(): int
	{
		return $this->ids[0];
	}
	
	public function offsetExists(mixed $offset): bool
	{
		return isset($this->ids[$offset]);
	}
	
	public function offsetGet(mixed $offset): mixed
	{
		return $this->ids[$offset];
	}
	
	public function offsetSet(mixed $offset, mixed $value): void
	{
		throw new LogicException('Worker IDs are read-only.');
	}
	
	public function offsetUnset(mixed $offset): void
	{
		throw new LogicException('Worker IDs are read-only.');
	}
}
