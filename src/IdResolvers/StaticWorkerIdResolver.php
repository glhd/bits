<?php

namespace Glhd\Bits\IdResolvers;

use Glhd\Bits\Config\WorkerIds;
use Glhd\Bits\Contracts\ResolvesWorkerIds;

class StaticWorkerIdResolver implements ResolvesWorkerIds
{
	protected WorkerIds $ids;
	
	public function __construct(int ...$ids)
	{
		$this->ids = new WorkerIds(...$ids);
	}
	
	public function get(...$lengths): WorkerIds
	{
		return $this->ids;
	}
}
