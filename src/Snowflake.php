<?php

namespace Glhd\Bits;

use Glhd\Bits\Config\GenericConfiguration;
use Glhd\Bits\Config\WorkerIds;
use Glhd\Bits\Contracts\Configuration;
use Glhd\Bits\Presets\Snowflakes;
use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Grammar;

class Snowflake extends Bits
{
	public function __construct(
		public readonly int $timestamp,
		public readonly int $datacenter_id,
		public readonly int $worker_id,
		public readonly int $sequence,
		?Snowflakes $config = null,
	) {
		parent::__construct(
			values: [0, $this->timestamp, $this->datacenter_id, $this->worker_id, $this->sequence],
			config: $config ?? app(Snowflakes::class),
		);
	}

	public function id(): int
	{
		return $this->id ??= $this->config->combine(
			0, $this->timestamp, $this->datacenter_id, $this->worker_id, $this->sequence
		);
	}
}
