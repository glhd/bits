<?php

namespace Glhd\Bits\Presets;

use Glhd\Bits\Config\GenericConfiguration;
use Glhd\Bits\Config\Segment;
use Glhd\Bits\Config\WorkerIds;
use Glhd\Bits\Contracts\MakesSnowflakes;
use Glhd\Bits\Contracts\ResolvesSequences;
use Glhd\Bits\Factories\BitsFactory;
use Illuminate\Support\Facades\Date;

class Snowflakes extends GenericConfiguration
{
	public function __construct()
	{
		parent::__construct(
			3,
			1,
			Segment::id(label: 'pad', length: 1),
			Segment::timestamp(label: 'timestamp', length: 41),
			Segment::id(label: 'datacenter', length: 5),
			Segment::id(label: 'worker', length: 5),
			Segment::sequence(label: 'sequence', length: 12),
		);
	}
	
	public function factory(array $config, ResolvesSequences $sequence): MakesSnowflakes
	{
		return new class(
			epoch: Date::parse(data_get($config, 'epoch'), 'UTC')->startOfDay(),
			ids: new WorkerIds(
				(int) (data_get($config, 'datacenter_id') ?? random_int(0, 31)),
				(int) (data_get($config, 'worker_id') ?? random_int(0, 31)),
			),
			config: $this,
			sequence: $sequence,
		) extends BitsFactory implements MakesSnowflakes {
		};
	}
}
