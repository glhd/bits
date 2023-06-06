<?php

namespace Glhd\Bits\Tests;

use Glhd\Bits\Contracts\ResolvesSequences;
use Glhd\Bits\Factory;
use Glhd\Bits\Testing\InMemorySequenceResolver;

trait ResolvesSequencesFromMemory
{
	public function setUpResolvesSequencesFromMemory(): void
	{
		app()->instance(ResolvesSequences::class, new InMemorySequenceResolver());
		app()->forgetInstance(Factory::class);
	}
}
