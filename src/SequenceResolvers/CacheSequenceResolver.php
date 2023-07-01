<?php

namespace Glhd\Bits\SequenceResolvers;

use Glhd\Bits\Contracts\ResolvesSequences;
use Illuminate\Support\Facades\Cache;

class CacheSequenceResolver implements ResolvesSequences
{
	public function next(int $timestamp): int
	{
		$key = "bits-seq:{$timestamp}";

		Cache::add($key, 0, now()->addSeconds(10));

		return Cache::increment($key) - 1;
	}
}
