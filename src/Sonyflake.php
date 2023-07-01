<?php

namespace Glhd\Bits;

use Glhd\Bits\Config\GenericConfiguration;
use Glhd\Bits\Config\WorkerIds;

class Sonyflake extends Bits
{
	public function __construct(
		int $timestamp,
		int $sequence,
		public readonly int $machine_id,
		GenericConfiguration $config,
	) {
		parent::__construct(
			new WorkerIds($this->machine_id),
			$timestamp,
			$sequence,
			$config,
		);
	}

	public function id(): int
	{
		return $this->id ??= $this->config->combine(
			$this->timestamp, $this->sequence, $this->machine_id
		);
	}
}
