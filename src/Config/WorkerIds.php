<?php

namespace Glhd\Bits\Config;

class WorkerIds
{
	/** @var int[] */
	public array $ids;
	
	public function __construct(int ...$ids)
	{
		$this->ids = $ids;
	}
}
