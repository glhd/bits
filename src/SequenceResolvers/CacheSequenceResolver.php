<?php

namespace Glhd\Bits\SequenceResolvers;

use Glhd\Bits\Contracts\ResolvesSequences;
use Illuminate\Contracts\Cache\Repository;

class CacheSequenceResolver implements ResolvesSequences
{
	public function __construct(
		protected Repository $cache
	) {
	}
	
	public function next(int $timestamp): int
	{
		$key = "glhd-bits-seq:{$timestamp}";
		
		$this->cache->add($key, 0, now()->addSeconds(10));
		
		return $this->cache->increment($key) - 1;
	}
}
