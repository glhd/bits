<?php

namespace Glhd\Bits;

use Glhd\Bits\Config\GenericConfiguration;
use Glhd\Bits\Config\WorkerIds;
use Glhd\Bits\Presets\Sonyflakes;

class Sonyflake extends Bits
{
	public function __construct(
		public readonly int $timestamp,
		public readonly int $sequence,
		public readonly int $machine_id,
		?Sonyflakes $config = null,
	) {
		parent::__construct(
			values: [$this->timestamp, $this->sequence, $this->machine_id],
			config: $config ?? app(Sonyflakes::class),
		);
	}

	public function id(): int
	{
		return $this->id ??= $this->config->combine(
			$this->timestamp, $this->sequence, $this->machine_id
		);
	}
}
