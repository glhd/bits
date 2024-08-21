<?php

namespace Glhd\Bits\Contracts;

use Glhd\Bits\Config\WorkerIds;

interface ResolvesWorkerIds
{
	public function get(...$lengths): WorkerIds;
}
