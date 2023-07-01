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

class CustomTest extends TestCase
{
	use ResolvesSequencesFromMemory;
	
	protected function setUp(): void
	{
		parent::setUp();
		
		$this->app->instance(MakesBits::class, $this->getBitsFactory(11));
	}
	
	public function test_it_generates_unique_ids(): void
	{
		$exists = [];
		$iterations = 10_000;
		
		for ($i = 0; $i < $iterations; $i++) {
			$exists[Bits::make()->id()] = true;
		}
		
		$this->assertCount($iterations, $exists);
	}
	
	public function test_it_generates_bits_in_the_expected_format(): void
	{
		$bits = Bits::make()->id();
		
		$this->assertGreaterThan(0, $bits);
		$this->assertLessThanOrEqual(9_223_372_036_854_775_807, $bits);
	}
	
	public function test_it_generates_bits_with_the_correct_id(): void
	{
		$factory1 = $this->getBitsFactory(random_int(0, 7));
		$factory2 = $this->getBitsFactory(random_int(8, 15));
		
		$bits1 = $factory1->make();
		$bits2 = $factory2->make();
		
		$this->assertEquals($factory1->ids->first(), $bits1->values[1]);
		$this->assertEquals($factory2->ids->first(), $bits2->values[1]);
	}
	
	public function test_it_can_parse_an_existing_snowflake(): void
	{
		$bits = Bits::fromId(1537200202186752);
		
		$this->assertEquals(93823254528, $bits->values[0]);
		$this->assertEquals(0, $bits->values[1]);
		$this->assertEquals(0, $bits->values[2]);
		
		$this->assertFalse(isset($bits->values[3]));
	}
	
	public function test_two_bits_with_same_id_are_considered_equal(): void
	{
		$bits1 = Bits::fromId(1537200202186752);
		$bits2 = Bits::fromId(1537200202186752);
		
		$this->assertTrue($bits1->is($bits2));
		$this->assertTrue($bits2->is($bits1));
	}
	
	public function test_bits_are_not_considered_equal_to_snowflake_with_same_value(): void
	{
		$bits = Bits::fromId(1537200202186752);
		$snowflake = Snowflake::fromId(1537200202186752);
		
		$this->assertFalse($bits->is($snowflake));
		$this->assertFalse($snowflake->is($bits));
	}
	
	public function test_valid_types_can_be_coerced_to_bits(): void
	{
		$direct = Bits::fromId(1537200202186752);
		$from_int = Bits::coerce(1537200202186752);
		$from_string = Bits::coerce('1537200202186752');
		$from_bits = Bits::coerce($direct);
		
		$this->assertTrue($direct->is($from_int));
		$this->assertTrue($direct->is($from_string));
		$this->assertTrue($direct->is($from_bits));
	}
	
	public function test_it_generates_predictable_snowflakes(): void
	{
		Date::setTestNow(now());
		
		$sequence = 0;
		
		$factory = $this->getBitsFactory(7, new TestingSequenceResolver($sequence));
		
		$bits_at_epoch1 = $factory->make();
		
		$this->assertEquals($bits_at_epoch1->id(), 0b0000000000000000000000000000000000000000000000000001110000000000);
		$this->assertEquals($bits_at_epoch1->values[0], 0);
		$this->assertEquals($bits_at_epoch1->values[1], 7);
		$this->assertEquals($bits_at_epoch1->values[2], 0);
		
		$bits_at_epoch2 = $factory->make();
		
		$this->assertEquals($bits_at_epoch2->id(), 0b0000000000000000000000000000000000000000000000000001110000000001);
		$this->assertEquals($bits_at_epoch2->values[0], 0);
		$this->assertEquals($bits_at_epoch2->values[1], 7);
		$this->assertEquals($bits_at_epoch2->values[2], 1);
		
		Date::setTestNow(now()->addMicrosecond());
		$bits_at_1us = $factory->make();
		
		$this->assertEquals($bits_at_1us->id(), 0b0000000000000000000000000000000000000000000000000101110000000010);
		$this->assertEquals($bits_at_1us->values[0], 1);
		$this->assertEquals($bits_at_1us->values[1], 7);
		$this->assertEquals($bits_at_1us->values[2], 2);
		
		Date::setTestNow(now()->addMicrosecond());
		$bits_at_2us = $factory->make();
		
		$this->assertEquals($bits_at_2us->id(), 0b0000000000000000000000000000000000000000000000001001110000000011);
		$this->assertEquals($bits_at_2us->values[0], 2);
		$this->assertEquals($bits_at_2us->values[1], 7);
		$this->assertEquals($bits_at_2us->values[2], 3);
	}
	
	protected function getBitsFactory(int $id, ResolvesSequences $sequence = null): GenericFactory
	{
		$config = new GenericConfig(
			precision: 6,
			unit: 1,
			segments: new Collection([
				Segment::timestamp('timestamp', 50),
				Segment::id('id', 4),
				Segment::sequence('sequence', 10),
			]),
		);
		
		return new GenericFactory(
			epoch: now(),
			ids: new WorkerIds($id),
			config: $config,
			sequence: $sequence ?? new InMemorySequenceResolver(),
		);
	}
}
