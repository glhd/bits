<?php

namespace Glhd\Bits\Config;

class SonyflakesConfig extends GenericConfig
{
	public function __construct()
	{
		parent::__construct(
			3,
			10,
			Segment::timestamp(label: 'timestamp', length: 40),
			Segment::sequence(label: 'sequence', length: 8),
			Segment::id(label: 'datacenter', length: 16),
		);
	}
}
