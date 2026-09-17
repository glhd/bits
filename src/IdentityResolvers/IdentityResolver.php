<?php

namespace Glhd\Bits\IdentityResolvers;

use Glhd\Bits\Config\WorkerIds;
use Glhd\Bits\Contracts\ResolvesIdentities;

/**
 * Resolves identities from a single integer.
 *
 * Every combination of worker/datacenter (or machine) IDs a configuration can hold maps
 * to exactly one index in [0, space->size()), so a resolver only has to produce that
 * index and the space takes care of splitting it back across segments. If you need to
 * source identities from somewhere Bits does not support — a StatefulSet ordinal, an
 * ECS task index, a row in your own table — this is the shortest way in.
 */
abstract class IdentityResolver implements ResolvesIdentities
{
	protected ?int $resolved_index = null;
	
	protected ?WorkerIds $resolved = null;
	
	public function __construct(
		protected IdentitySpace $space,
	) {
	}
	
	public function space(): IdentitySpace
	{
		return $this->space;
	}
	
	public function identities(): WorkerIds
	{
		$index = $this->value();
		
		// Hold onto the result so that callers asking on every generated ID get the
		// same instance back, rather than an equal one
		if ($index !== $this->resolved_index) {
			$this->resolved_index = $index;
			$this->resolved = $this->space->at($index);
		}
		
		return $this->resolved;
	}
	
	public function release(): void
	{
		// There is nothing to release by default
	}
	
	/** An index into the identity space, between 0 and space->size() - 1 */
	abstract protected function value(): int;
}
