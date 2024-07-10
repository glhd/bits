<?php

namespace Glhd\Bits\Tests\Unit;

use Carbon\CarbonInterface;
use Glhd\Bits\Config\GenericConfig;
use Glhd\Bits\Config\Segment;
use Glhd\Bits\Config\SnowflakesConfig;
use Glhd\Bits\Config\WorkerIds;
use Glhd\Bits\Contracts\MakesBits;
use Glhd\Bits\Contracts\ResolvesSequences;
use Glhd\Bits\Factories\GenericFactory;
use Glhd\Bits\Factories\SnowflakeFactory;
use Glhd\Bits\SequenceResolvers\TestingSequenceResolver;
use Glhd\Bits\Tests\TestCase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;

class TimestampTest extends TestCase
{
	protected int $current_sequence = 0;
	
	public function test_it_parses_timestamps_correctly(): void
	{
		$microsecond = 842_000;
		$factory = $this->factory();
		
		// Only the 842 will be preserved because snowflakes are only
		// millisecond-precise, not microsecond-precise
		Date::setTestNow(now()->microseconds(842000));
		
		$sequence = 0;
		
		$factory = new SnowflakeFactory(
			epoch: now()->microseconds(0),
			datacenter_id: 1,
			worker_id: 15,
			config: app(SnowflakesConfig::class),
			sequence: new TestingSequenceResolver($sequence)
		);
		
		$this->app->instance(MakesBits::class, $factory);
		
		$snowflake_at_epoch = $factory->make();
		
		$instance = $snowflake_at_epoch->toCarbon();
		
		$this->assertEquals(now()->format('U.u'), $instance->format('U.u'));
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
