<?php

namespace Glhd\Bits\SequenceResolvers;

use Glhd\Bits\Contracts\ResolvesSequences;

class TestingSequenceResolver implements ResolvesSequences
{
	public int $sequence = 0;
	
	public function __construct(int &$sequence)
	{
		$this->sequence =& $sequence;
	}
	
	public function next(int $timestamp): int
	{
		return $this->sequence++;
	}
}
