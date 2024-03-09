<?php

namespace Glhd\Bits\Tests\Unit;

use Carbon\CarbonInterval;
use Exception;
use Glhd\Bits\Config\SonyflakesConfig;
use Glhd\Bits\Contracts\MakesSonyflakes;
use Glhd\Bits\Contracts\ResolvesSequences;
use Glhd\Bits\Factories\SonyflakeFactory;
use Glhd\Bits\SequenceResolvers\TestingSequenceResolver;
use Glhd\Bits\Sonyflake;
use Glhd\Bits\Tests\ResolvesSequencesFromMemory;
use Glhd\Bits\Tests\TestCase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Sleep;

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
		Date::setTestNow(now());
		
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
		$factory1 = new SonyflakeFactory(now(), random_int(0, 32_000), app(SonyflakesConfig::class), app(ResolvesSequences::class));
		$factory2 = new SonyflakeFactory(now(), random_int(32_001, 65_535), app(SonyflakesConfig::class), app(ResolvesSequences::class));
		
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
	
	public function test_two_sonyflakes_with_same_id_are_considered_equal(): void
	{
		$sonyflake1 = Sonyflake::fromId(1537200202186752);
		$sonyflake2 = Sonyflake::fromId(1537200202186752);
		
		$this->assertTrue($sonyflake1->is($sonyflake2));
		$this->assertTrue($sonyflake2->is($sonyflake1));
	}
	
	public function test_valid_types_can_be_coerced_to_sonyflake(): void
	{
		$direct = Sonyflake::fromId(1537200202186752);
		$from_int = Sonyflake::coerce(1537200202186752);
		$from_string = Sonyflake::coerce('1537200202186752');
		$from_bits = Sonyflake::coerce($direct);
		
		$this->assertTrue($direct->is($from_int));
		$this->assertTrue($direct->is($from_string));
		$this->assertTrue($direct->is($from_bits));
	}
	
	public function test_it_generates_predictable_sonyflakes(): void
	{
		Date::setTestNow(now());
		
		$sequence = 0;
		
		$factory = new SonyflakeFactory(now(), 1, app(SonyflakesConfig::class), new TestingSequenceResolver($sequence));
		
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
	
	public function test_it_sleeps_10ms_when_sequence_limit_is_reached(): void
	{
		Date::setTestNow(now());
		
		$sequence = 255;
		
		Sleep::whenFakingSleep(function() use (&$sequence) {
			$sequence = 0;
		});
		
		$this->app->instance(ResolvesSequences::class, new TestingSequenceResolver($sequence));
		
		Sonyflake::make();
		
		Sleep::assertNeverSlept();
		
		Sonyflake::make();
		
		Sleep::assertSlept(fn(CarbonInterval $interval) => $interval->totalMicroseconds === 10000);
	}
	
	public function test_a_snowflake_can_be_created_for_a_specific_timestamp(): void
	{
		$factory = $this->app->make(MakesSonyflakes::class);
		
		$sonyflake = $factory->makeFromTimestamp($factory->epoch->toImmutable()->addMilliseconds(10));
		
		$this->assertEquals(1, $sonyflake->timestamp);
		
		$sonyflake = $factory->makeFromTimestamp($factory->epoch->toImmutable()->addMilliseconds(420));
		
		$this->assertEquals(42, $sonyflake->timestamp);
	}

	public function test_it_can_freeze_a_sonyflake(): void
	{
		$this->assertNotSame((string) Sonyflake::make(), (string) Sonyflake::make());
		$this->assertNotSame(Sonyflake::make(), Sonyflake::make());

		$sonyflake = Sonyflake::freeze();

		$this->assertSame($sonyflake, Sonyflake::make());
		$this->assertSame(Sonyflake::make(), Sonyflake::make());
		$this->assertSame((string) $sonyflake, (string) Sonyflake::make());
		$this->assertSame((string) Sonyflake::make(), (string) Sonyflake::make());

		Sonyflake::createNormally();

		$this->assertNotSame(Sonyflake::make(), Sonyflake::make());
		$this->assertNotSame((string) Sonyflake::make(), (string) Sonyflake::make());
	}

	public function test_it_can_freeze_sonyflakes_in_a_closure(): void
	{
		$sonyflakes = [];

		$sonyflake = Sonyflake::freeze(function($sonyflake) use (&$sonyflakes) {
			$sonyflakes[] = $sonyflake;
			$sonyflakes[] = Sonyflake::make();
			$sonyflakes[] = Sonyflake::make();
		});

		$this->assertSame($sonyflake, $sonyflakes[0]);
		$this->assertSame((string) $sonyflake, (string) $sonyflakes[0]);
		$this->assertSame((string) $sonyflakes[0], (string) $sonyflakes[1]);
		$this->assertSame($sonyflakes[0], $sonyflakes[1]);
		$this->assertSame((string) $sonyflakes[0], (string) $sonyflakes[1]);
		$this->assertSame($sonyflakes[1], $sonyflakes[2]);
		$this->assertSame((string) $sonyflakes[1], (string) $sonyflakes[2]);
		$this->assertNotSame(Sonyflake::make(), Sonyflake::make());
		$this->assertNotSame((string) Sonyflake::make(), (string) Sonyflake::make());

		Sonyflake::createNormally();
	}

	public function test_it_creates_sonyflakes_normally_after_failure_within_freeze_method(): void
	{
		try {
			Sonyflake::freeze(function() {
				Sonyflake::createUsing(fn() => Sonyflake::coerce('1234'));
				$this->assertSame(1234, Sonyflake::make()->id());
				throw new Exception('Something failed.');
			});
		} catch (Exception) {
			$this->assertNotSame(1234, Sonyflake::make()->id());
		}
	}

	public function test_it_can_specify_a_sequence_of_sonyflakes_to_utilise(): void
	{
		Sonyflake::createUsingSequence([
			0 => ($zeroth = Sonyflake::make()),
			1 => ($first = Sonyflake::make()),
			// just generate a random one here...
			3 => ($third = Sonyflake::make()),
			// continue to generate random uuids...
		]);

		$retrieved = Sonyflake::make();
		$this->assertSame($zeroth, $retrieved);
		$this->assertSame((string) $zeroth, (string) $retrieved);

		$retrieved = Sonyflake::make();
		$this->assertSame($first, $retrieved);
		$this->assertSame((string) $first, (string) $retrieved);

		$retrieved = Sonyflake::make();
		$this->assertFalse(in_array($retrieved, [$zeroth, $first, $third], true));
		$this->assertFalse(in_array((string) $retrieved, [(string) $zeroth, (string) $first, (string) $third], true));

		$retrieved = Sonyflake::make();
		$this->assertSame($third, $retrieved);
		$this->assertSame((string) $third, (string) $retrieved);

		$retrieved = Sonyflake::make();
		$this->assertFalse(in_array($retrieved, [$zeroth, $first, $third], true));
		$this->assertFalse(in_array((string) $retrieved, [(string) $zeroth, (string) $first, (string) $third], true));

		Sonyflake::createNormally();
	}

	public function test_it_can_specify_a_fallback_for_a_sequence(): void
	{
		Sonyflake::createUsingSequence([Sonyflake::make(), Sonyflake::make()], fn() => throw new Exception('Out of Sonyflakes.'));
		Sonyflake::make();
		Sonyflake::make();

		try {
			$this->expectExceptionMessage('Out of Sonyflakes.');
			Sonyflake::make();
			$this->fail();
		} finally {
			Sonyflake::createNormally();
		}
	}
}
