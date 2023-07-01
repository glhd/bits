<?php

namespace Glhd\Bits\Tests\Unit;

use Glhd\Bits\Contracts\MakesSonyflakes;
use Glhd\Bits\Contracts\ResolvesSequences;
use Glhd\Bits\Factories\SonyflakeFactory;
use Glhd\Bits\Presets\Sonyflakes;
use Glhd\Bits\Sonyflake;
use Glhd\Bits\Tests\ResolvesSequencesFromMemory;
use Glhd\Bits\Tests\TestCase;
use Illuminate\Support\Facades\Date;

class SonyflakeTest extends TestCase
{
	use ResolvesSequencesFromMemory;
	
	public function test_global_helper_function(): void
	{
		$this->assertInstanceOf(MakesSonyflakes::class, sonyflake());
		$this->assertInstanceOf(Sonyflake::class, sonyflake()->make());
		$this->assertInstanceOf(Sonyflake::class, sonyflake(1));
	}
	
	public function test_it_generates_unique_ids(): void
	{
		$exists = [];
		$iterations = 10_000;
		
		for ($i = 0; $i < $iterations; $i++) {
			$exists[Sonyflake::make()->id()] = true;
		}
		
		$this->assertCount($iterations, $exists);
	}
	
	public function test_it_generates_sonyflakes_in_the_expected_format(): void
	{
		$Sonyflake = Sonyflake::make()->id();
		
		$this->assertGreaterThan(0, $Sonyflake);
		$this->assertLessThanOrEqual(9_223_372_036_854_775_807, $Sonyflake);
	}
	
	public function test_it_generates_sonyflakes_with_the_correct_machine_id(): void
	{
		$factory1 = new SonyflakeFactory(now(), random_int(0, 32_000), app(Sonyflakes::class), app(ResolvesSequences::class));
		$factory2 = new SonyflakeFactory(now(), random_int(32_001, 65_535), app(Sonyflakes::class), app(ResolvesSequences::class));
		
		$Sonyflake1 = $factory1->make();
		$Sonyflake2 = $factory2->make();
		
		$this->assertEquals($factory1->machine_id, $Sonyflake1->machine_id);
		$this->assertEquals($factory2->machine_id, $Sonyflake2->machine_id);
	}
	
	public function test_it_can_parse_an_existing_sonyflake(): void
	{
		$sonyflake = Sonyflake::fromId(56705782302306333);
		
		$this->assertEquals(3379928010, $sonyflake->timestamp);
		$this->assertEquals(214, $sonyflake->sequence);
		$this->assertEquals(61469, $sonyflake->machine_id);
	}
	
	public function test_it_generates_predictable_sonyflakes(): void
	{
		Date::setTestNow(now());
		
		$sequence = 0;
		
		$factory = new SonyflakeFactory(now(), 1, app(Sonyflakes::class), new class($sequence) implements ResolvesSequences {
			public function __construct(public int &$sequence)
			{
			}
			
			public function next(int $timestamp): int
			{
				return $this->sequence++;
			}
		});
		
		$sonyflake_at_epoch1 = $factory->make();
		
		$this->assertEquals($sonyflake_at_epoch1->id(), 0b0000000000000000000000000000000000000000000000000000000000000001);
		$this->assertEquals($sonyflake_at_epoch1->timestamp, 0);
		$this->assertEquals($sonyflake_at_epoch1->machine_id, 1);
		$this->assertEquals($sonyflake_at_epoch1->sequence, 0);
		
		$sonyflake_at_epoch2 = $factory->make();
		
		$this->assertEquals($sonyflake_at_epoch2->id(), 0b0000000000000000000000000000000000000000000000010000000000000001);
		$this->assertEquals($sonyflake_at_epoch2->timestamp, 0);
		$this->assertEquals($sonyflake_at_epoch2->machine_id, 1);
		$this->assertEquals($sonyflake_at_epoch2->sequence, 1);
		
		Date::setTestNow(now()->addMilliseconds(10));
		$sonyflake_at_10ms = $factory->make();
		
		$this->assertEquals($sonyflake_at_10ms->id(), 0b0000000000000000000000000000000000000001000000100000000000000001);
		$this->assertEquals($sonyflake_at_10ms->timestamp, 1);
		$this->assertEquals($sonyflake_at_10ms->machine_id, 1);
		$this->assertEquals($sonyflake_at_10ms->sequence, 2);
		
		Date::setTestNow(now()->addMilliseconds(10));
		$sonyflake_at_20ms = $factory->make();
		
		$this->assertEquals($sonyflake_at_20ms->id(), 0b0000000000000000000000000000000000000010000000110000000000000001);
		$this->assertEquals($sonyflake_at_20ms->timestamp, 2);
		$this->assertEquals($sonyflake_at_10ms->machine_id, 1);
		$this->assertEquals($sonyflake_at_20ms->sequence, 3);
	}
}
