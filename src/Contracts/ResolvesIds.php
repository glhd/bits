<?php

namespace Glhd\Bits\Contracts;

use Glhd\Bits\Config\WorkerIds;

interface ResolvesIds
{
	public function get(...$lengths): WorkerIds;
}
