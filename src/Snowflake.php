<?php

namespace Glhd\Bits;

use Glhd\Bits\Config\GenericConfiguration;
use Glhd\Bits\Config\WorkerIds;
use Glhd\Bits\Presets\Snowflakes;
use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Grammar;

class Snowflake extends Bits
{
	public function __construct(
		int $timestamp,
		public readonly int $datacenter_id,
		public readonly int $worker_id,
		int $sequence,
		GenericConfiguration $config,
	) {
		parent::__construct(
			$config,
			0,
			$timestamp,
			$this->datacenter_id,
			$this->worker_id,
			$sequence,
		);
	}

	public function id(): int
	{
		return $this->id ??= $this->config->combine(
			0, $this->timestamp, $this->datacenter_id, $this->worker_id, $this->sequence
		);
	}
}
