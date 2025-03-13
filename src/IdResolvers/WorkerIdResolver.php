<?php

namespace Glhd\Bits\IdResolvers;

use Glhd\Bits\Config\WorkerIds;
use Glhd\Bits\Contracts\ResolvesWorkerIds;

abstract class WorkerIdResolver implements ResolvesWorkerIds
{
	abstract protected function value(): int;
	
	public function get(...$lengths): WorkerIds
	{
		$value = $this->value();
		$ids = [];
		
		foreach (array_reverse($lengths) as $length) {
			$bitmask = (1 << $length) - 1;
			array_unshift($ids, $value & $bitmask);
			$value = $value >> $length;
		}
		
		return new WorkerIds(...$ids);
	}
}
