<?php

namespace Glhd\Bits\IdentityResolvers;

use Glhd\Bits\Config\WorkerIds;
use Glhd\Bits\Contracts\ResolvesIdentities;

/**
 * Returns the same IDs for the lifetime of the process. This is the behavior Bits had
 * before identities became claimable, and it is still the right choice whenever you
 * assign worker and datacenter IDs yourself (a fixed fleet, a static container index,
 * a Kubernetes StatefulSet ordinal).
 *
 * Nothing stops two processes from being given the same IDs, so pair this with a
 * sequence resolver that coordinates across processes (see CacheSequenceResolver).
 */
class FixedIdentityResolver implements ResolvesIdentities
{
	public static function make(int ...$ids): static
	{
		return new static(new WorkerIds(...$ids));
	}
	
	public function __construct(
		public readonly WorkerIds $ids,
	) {
	}
	
	public function identities(): WorkerIds
	{
		return $this->ids;
	}
	
	public function release(): void
	{
		// There is nothing to release
	}
}
