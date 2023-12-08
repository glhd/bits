<?php

namespace Glhd\Bits\Config;

use Illuminate\Support\Collection;

class SonyflakesConfig extends GenericConfig
{
	public function __construct()
	{
		parent::__construct(
			precision: 3,
			unit: 10,
			segments: new Collection([
				Segment::timestamp(label: 'timestamp', length: 40),
				Segment::sequence(label: 'sequence', length: 8),
				Segment::id(label: 'datacenter', length: 16),
			]),
		);
	}
}
