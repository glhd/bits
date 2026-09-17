<?php

namespace Glhd\Bits\IdentityResolvers;

use Illuminate\Contracts\Cache\Repository;

/**
 * An identity that this process currently holds in the cache, along with everything
 * needed to renew it or give it back.
 */
class Claim
{
	public function __construct(
		public readonly string $scope,
		public readonly int $index,
		public readonly string $key,
		public readonly string $owner,
		public readonly Repository $cache,
		public float $verified_at,
	) {
	}
	
	/** True when the cache still shows this process as the owner */
	public function held(): bool
	{
		return $this->cache->get($this->key) === $this->owner;
	}
	
	/** Push the expiration back, but only while we are still the owner */
	public function renew(int $ttl): bool
	{
		if (! $this->held()) {
			return false;
		}
		
		return $this->cache->put($this->key, $this->owner, $ttl);
	}
	
	/** Hand the identity back so that another process can claim it */
	public function release(): void
	{
		if ($this->held()) {
			$this->cache->forget($this->key);
		}
	}
}
