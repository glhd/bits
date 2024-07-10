<?php

namespace Glhd\Bits\Tests\Unit;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Glhd\Bits\Config\GenericConfig;
use Glhd\Bits\Config\Segment;
use Glhd\Bits\Config\WorkerIds;
use Glhd\Bits\Contracts\MakesBits;
use Glhd\Bits\Contracts\ResolvesSequences;
use Glhd\Bits\Factories\GenericFactory;
use Glhd\Bits\SequenceResolvers\TestingSequenceResolver;
use Glhd\Bits\Tests\TestCase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;

class TimestampTest extends TestCase
{
	protected int $current_sequence = 0;
	
	public function test_it_parses_timestamps_correctly(): void
	{
		// Cases = precision, unit, seconds, microseconds, expected
		// All microsecond digits are < 5 to ensure consistent rounding
		$cases = [
			// Single-unit precision, start of epoch
			[6, 1, 0, 123_321, '0.123321'],
			[5, 1, 0, 123_321, '0.123320'],
			[4, 1, 0, 123_321, '0.123300'],
			[3, 1, 0, 123_321, '0.123000'],
			[2, 1, 0, 123_321, '0.120000'],
			[1, 1, 0, 123_321, '0.100000'],
			[0, 1, 0, 123_321, '0.000000'],
			
			// Single-unit precision, varying moments in time
			[6, 1, 123, 123_321, '123.123321'],
			[5, 1, 321, 123_321, '321.123320'],
			[4, 1, 456, 123_321, '456.123300'],
			[3, 1, 654, 123_321, '654.123000'],
			[2, 1, 789, 123_321, '789.120000'],
			[1, 1, 987, 123_321, '987.100000'],
			[0, 1, 123456789, 123_321, '123456789.000000'],
			
			// 10-unit precision, start of epoch
			[6, 10, 0, 123_321, '0.123320'],
			[5, 10, 0, 123_321, '0.123300'],
			[4, 10, 0, 123_321, '0.123000'],
			[3, 10, 0, 123_321, '0.120000'],
			[2, 10, 0, 123_321, '0.100000'],
			[1, 10, 0, 123_321, '0.000000'],
			
			// 10-unit precision, varying moments in time
			[6, 10, 123, 123_321, '123.123320'],
			[5, 10, 321, 123_321, '321.123300'],
			[4, 10, 456, 123_321, '456.123000'],
			[3, 10, 654, 123_321, '654.120000'],
			[2, 10, 789, 123_321, '789.100000'],
			[1, 10, 123456789, 123_321, '123456789.000000'],
			
			// 100-unit precision, start of epoch
			[6, 100, 0, 123_321, '0.123300'],
			[5, 100, 0, 123_321, '0.123000'],
			[4, 100, 0, 123_321, '0.120000'],
			[3, 100, 0, 123_321, '0.100000'],
			[2, 100, 0, 123_321, '0.000000'],
			
			// 100-unit precision, varying moments in time
			[6, 100, 123, 123_321, '123.123300'],
			[5, 100, 321, 123_321, '321.123000'],
			[4, 100, 456, 123_321, '456.120000'],
			[3, 100, 654, 123_321, '654.100000'],
			[2, 100, 123456789, 123_321, '123456789.000000'],
		];
		
		$unix_epoch = CarbonImmutable::createFromTimestampUTC(0)->microseconds(0);
		
		foreach ($cases as [$precision, $unit, $second, $microsecond, $expected]) {
			// Create a factory thats epoch is at the start of the second, and
			// lock time for that factory to the current "test now"
			$factory = $this->factory(
				precision: $precision,
				unit: $unit,
				epoch: $unix_epoch
			);
			$factory->setTestNow($unix_epoch->addSeconds($second)->addMicroseconds($microsecond));
			
			$instance = $factory->make()->toCarbon();
			
			$this->assertEquals($expected, $instance->format('U.u'));
		}
	}
	
	protected function factory(
		int $precision = 6,
		int $unit = 1,
		?Collection $segments = null,
		?CarbonInterface $epoch = null,
		array $ids = [1],
		?ResolvesSequences $sequence = null,
	): GenericFactory {
		$config = new GenericConfig(
			precision: $precision,
			unit: $unit,
			segments: $segments ?? new Collection([
			Segment::timestamp('timestamp', 50),
			Segment::id('id', 4),
			Segment::sequence('sequence', 10),
		]),
		);
		
		$factory = new GenericFactory(
			epoch: $epoch ?? now(),
			ids: new WorkerIds(...$ids),
			config: $config,
			sequence: $sequence ?? new TestingSequenceResolver($this->current_sequence),
		);
		
		return $this->app->instance(MakesBits::class, $factory);
	}
}
