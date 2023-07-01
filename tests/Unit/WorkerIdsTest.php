<?php

namespace Glhd\Bits\Tests\Unit;

use Glhd\Bits\Bits;
use Glhd\Bits\Config\GenericConfig;
use Glhd\Bits\Config\Segment;
use Glhd\Bits\Config\WorkerIds;
use Glhd\Bits\Contracts\MakesBits;
use Glhd\Bits\Contracts\ResolvesSequences;
use Glhd\Bits\Factories\GenericFactory;
use Glhd\Bits\SequenceResolvers\InMemorySequenceResolver;
use Glhd\Bits\SequenceResolvers\TestingSequenceResolver;
use Glhd\Bits\Snowflake;
use Glhd\Bits\Tests\ResolvesSequencesFromMemory;
use Glhd\Bits\Tests\TestCase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use LogicException;

class WorkerIdsTest extends TestCase
{
	public function test_worker_ids_can_be_read_as_an_array(): void
	{
		$ids = new WorkerIds(3, 6, 9 ,12);
		
		$this->assertTrue(isset($ids[0]));
		$this->assertTrue(isset($ids[1]));
		$this->assertTrue(isset($ids[2]));
		$this->assertTrue(isset($ids[3]));
		$this->assertFalse(isset($ids[4]));
		
		$this->assertEquals(3, $ids[0]);
		$this->assertEquals(6, $ids[1]);
		$this->assertEquals(9, $ids[2]);
		$this->assertEquals(12, $ids[3]);
	}
	
	public function test_worker_ids_cannot_be_set_with_array_syntax(): void
	{
		$this->expectException(LogicException::class);
		
		$ids = new WorkerIds(3, 6, 9, 12);
		
		$ids[0] = 1;
	}
	
	public function test_worker_ids_cannot_be_unset_with_array_syntax(): void
	{
		$this->expectException(LogicException::class);
		
		$ids = new WorkerIds(3, 6, 9, 12);
		
		unset($ids[0]);
	}
}
