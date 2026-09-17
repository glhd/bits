<?php

namespace Glhd\Bits\Config;

enum SegmentType
{
	case Id;
	case Timestamp;
	case Sequence;
	
	/**
	 * Padding segments are always zero. They exist so that a configuration can
	 * reserve bits (most commonly the sign bit) without those bits being
	 * mistaken for part of the worker/datacenter ID space.
	 */
	case Pad;
}
