<?php

namespace Glhd\Bits\Presets;

use Glhd\Bits\Config\GenericConfiguration;
use Glhd\Bits\Config\Segment;
use Glhd\Bits\Config\WorkerIds;
use Glhd\Bits\Contracts\MakesSonyflakes;
use Glhd\Bits\Contracts\ResolvesSequences;
use Glhd\Bits\Factories\BitsFactory;
use Illuminate\Support\Facades\Date;

class Sonyflakes extends GenericConfiguration
{
	public function __construct()
	{
		parent::__construct(
			3,
			10,
			Segment::timestamp(label: 'timestamp', length: 39),
			Segment::sequence(label: 'sequence', length: 8),
			Segment::id(label: 'datacenter', length: 16),
		);
	}
	
	public function factory(array $config, ResolvesSequences $sequence): MakesSonyflakes
	{
		return new class(
			epoch: Date::parse(data_get($config, 'epoch'), 'UTC')->startOfDay(),
			ids: new WorkerIds((int) (data_get($config, 'worker_id') ?? random_int(0, 65535))),
			config: $this,
			sequence: $sequence,
		) extends BitsFactory implements MakesSonyflakes {
		};
	}
}
