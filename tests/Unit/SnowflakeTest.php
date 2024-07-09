<?php

namespace Glhd\Bits\Tests\Unit;

use Carbon\CarbonInterval;
use Glhd\Bits\Config\SnowflakesConfig;
use Glhd\Bits\Contracts\MakesSnowflakes;
use Glhd\Bits\Contracts\ResolvesSequences;
use Glhd\Bits\Factories\SnowflakeFactory;
use Glhd\Bits\SequenceResolvers\InMemorySequenceResolver;
use Glhd\Bits\SequenceResolvers\TestingSequenceResolver;
use Glhd\Bits\Snowflake;
use Glhd\Bits\Sonyflake;
use Glhd\Bits\Tests\ResolvesSequencesFromMemory;
use Glhd\Bits\Tests\TestCase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Sleep;

class SnowflakeTest extends TestCase
{
	use ResolvesSequencesFromMemory;
	
	public function test_global_helper_function(): void
	{
		$this->assertInstanceOf(MakesSnowflakes::class, snowflake());
		$this->assertInstanceOf(Snowflake::class, snowflake()->make());
		$this->assertInstanceOf(Snowflake::class, snowflake(1));
	}
	
	public function test_it_generates_unique_ids(): void
	{
		$exists = [];
		$iterations = 10_000;
		
		for ($i = 0; $i < $iterations; $i++) {
			$exists[Snowflake::make()->id()] = true;
		}
		
		$this->assertCount($iterations, $exists);
	}
	
	public function test_it_generates_snowflakes_in_the_expected_format(): void
	{
		$snowflake = Snowflake::make()->id();
		
		$this->assertGreaterThan(0, $snowflake);
		$this->assertLessThanOrEqual(9_223_372_036_854_775_807, $snowflake);
	}
	
	public function test_it_generates_snowflakes_with_the_correct_datacenter_and_worker_ids(): void
	{
		$factory1 = new SnowflakeFactory(now(), random_int(0, 7), random_int(0, 7), app(SnowflakesConfig::class), app(ResolvesSequences::class));
		$factory2 = new SnowflakeFactory(now(), random_int(8, 15), random_int(8, 15), app(SnowflakesConfig::class), app(ResolvesSequences::class));
		
		$snowflake1 = $factory1->make();
		$snowflake2 = $factory2->make();
		
		$this->assertEquals($factory1->datacenter_id, $snowflake1->datacenter_id);
		$this->assertEquals($factory1->worker_id, $snowflake1->worker_id);
		
		$this->assertEquals($factory2->datacenter_id, $snowflake2->datacenter_id);
		$this->assertEquals($factory2->worker_id, $snowflake2->worker_id);
	}
	
	public function test_it_can_parse_an_existing_snowflake(): void
	{
		$snowflake = Snowflake::fromId(1537200202186752);
		
		$this->assertEquals(0, $snowflake->datacenter_id);
		$this->assertEquals(0, $snowflake->worker_id);
		$this->assertEquals(0, $snowflake->sequence);
	}
	
	public function test_two_snowflakes_with_same_id_are_considered_equal(): void
	{
		$snowflake1 = Snowflake::fromId(1537200202186752);
		$snowflake2 = Snowflake::fromId(1537200202186752);
		
		$this->assertTrue($snowflake1->is($snowflake2));
		$this->assertTrue($snowflake2->is($snowflake1));
	}
	
	public function test_snowflakes_are_not_considered_equal_to_sonyflakes_with_same_value(): void
	{
		$snowflake = Snowflake::fromId(1537200202186752);
		$sonyflake = Sonyflake::fromId(1537200202186752);
		
		$this->assertFalse($snowflake->is($sonyflake));
		$this->assertFalse($sonyflake->is($snowflake));
	}
	
	public function test_valid_types_can_be_coerced_to_snowflake(): void
	{
		$direct = Snowflake::fromId(1537200202186752);
		$from_int = Snowflake::coerce(1537200202186752);
		$from_string = Snowflake::coerce('1537200202186752');
		$from_bits = Snowflake::coerce($direct);
		
		$this->assertTrue($direct->is($from_int));
		$this->assertTrue($direct->is($from_string));
		$this->assertTrue($direct->is($from_bits));
	}
	
	public function test_it_generates_predictable_snowflakes(): void
	{
		Date::setTestNow(now());
		
		$sequence = 0;
		
		$factory = new SnowflakeFactory(
			epoch: now(),
			datacenter_id: 1,
			worker_id: 15,
			config: app(SnowflakesConfig::class),
			sequence: new TestingSequenceResolver($sequence)
		);
		
		$factory->setTestNow(now());
		
		$snowflake_at_epoch1 = $factory->make();
		
		$this->assertEquals($snowflake_at_epoch1->id(), 0b0000000000000000000000000000000000000000000000101111000000000000);
		$this->assertEquals($snowflake_at_epoch1->timestamp, 0);
		$this->assertEquals($snowflake_at_epoch1->datacenter_id, 1);
		$this->assertEquals($snowflake_at_epoch1->worker_id, 15);
		$this->assertEquals($snowflake_at_epoch1->sequence, 0);
		
		$snowflake_at_epoch2 = $factory->make();
		
		$this->assertEquals($snowflake_at_epoch2->id(), 0b0000000000000000000000000000000000000000000000101111000000000001);
		$this->assertEquals($snowflake_at_epoch2->timestamp, 0);
		$this->assertEquals($snowflake_at_epoch2->datacenter_id, 1);
		$this->assertEquals($snowflake_at_epoch2->worker_id, 15);
		$this->assertEquals($snowflake_at_epoch2->sequence, 1);
		
		$factory->setTestNow(now()->addMillisecond());
		
		$snowflake_at_1ms = $factory->make();
		
		$this->assertEquals($snowflake_at_1ms->id(), 0b0000000000000000000000000000000000000000010000101111000000000010);
		$this->assertEquals($snowflake_at_1ms->timestamp, 1);
		$this->assertEquals($snowflake_at_1ms->datacenter_id, 1);
		$this->assertEquals($snowflake_at_1ms->worker_id, 15);
		$this->assertEquals($snowflake_at_1ms->sequence, 2);
		
		$factory->setTestNow(now()->addMilliseconds(2));
		
		$snowflake_at_2ms = $factory->make();
		
		$this->assertEquals($snowflake_at_2ms->id(), 0b0000000000000000000000000000000000000000100000101111000000000011);
		$this->assertEquals($snowflake_at_2ms->timestamp, 2);
		$this->assertEquals($snowflake_at_2ms->datacenter_id, 1);
		$this->assertEquals($snowflake_at_2ms->worker_id, 15);
		$this->assertEquals($snowflake_at_2ms->sequence, 3);
	}
	
	public function test_it_generates_using_real_now_rather_than_test_now(): void
	{
		Date::setTestNow(now()->subMinute());
		
		$factory = new SnowflakeFactory(
			epoch: now(),
			datacenter_id: 1,
			worker_id: 15,
			config: app(SnowflakesConfig::class),
			sequence: new InMemorySequenceResolver(),
		);
		
		$factory->setTestNow(now());
		$snowflake_at_epoch = $factory->make();
		
		$this->assertEquals($snowflake_at_epoch->id(), 0b0000000000000000000000000000000000000000000000101111000000000000);
		$this->assertEquals($snowflake_at_epoch->timestamp, 0);
		
		$factory->setTestNow(null);
		$snowflake_at_real_now = $factory->make();
		
		$this->assertGreaterThan(0b0000000000000000000000000000000000000000000000101111000000000000, $snowflake_at_real_now->id());
		$this->assertGreaterThanOrEqual(60_000, $snowflake_at_real_now->timestamp); // 1 min = 60,000 ms
	}
	
	public function test_it_sleeps_1ms_when_sequence_limit_is_reached(): void
	{
		Date::setTestNow(now());
		
		$sequence = 4095;
		
		Sleep::whenFakingSleep(function() use (&$sequence) {
			$sequence = 0;
		});
		
		$this->app->instance(ResolvesSequences::class, new TestingSequenceResolver($sequence));
		
		Snowflake::make();
		
		Sleep::assertNeverSlept();
		
		Snowflake::make();
		
		Sleep::assertSlept(fn(CarbonInterval $interval) => (int) $interval->totalMicroseconds === 1000);
	}
	
	public function test_a_snowflake_can_be_created_for_a_specific_timestamp(): void
	{
		$factory = $this->app->make(MakesSnowflakes::class);
		
		$snowflake = $factory->makeFromTimestamp($factory->epoch->toImmutable()->addMillisecond());
		
		$this->assertEquals(1, $snowflake->timestamp);
		
		$snowflake = $factory->makeFromTimestamp($factory->epoch->toImmutable()->addMilliseconds(42));
		
		$this->assertEquals(42, $snowflake->timestamp);
	}
	
	public function test_a_snowflake_can_be_serialized_to_json(): void
	{
		$snowflake = Snowflake::make();
		
		$string = '"'.(string) $snowflake.'"';
		
		$this->assertEquals($string, $snowflake->toJson());
		$this->assertEquals($string, json_encode($snowflake));
	}
}
