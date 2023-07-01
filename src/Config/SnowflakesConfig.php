<?php

namespace Glhd\Bits\Config;

class SnowflakesConfig extends GenericConfig
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
}
