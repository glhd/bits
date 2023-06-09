<?php

namespace Glhd\Bits\Tests;

use Glhd\Bits\Contracts\ResolvesSequences;
use Glhd\Bits\SequenceResolvers\InMemorySequenceResolver;

trait ResolvesSequencesFromMemory
{
	public function setUpResolvesSequencesFromMemory(): void
	{
		app()->instance(ResolvesSequences::class, new InMemorySequenceResolver());
	}
}
