<?php

namespace Glhd\Bits\Contracts;

use Glhd\Bits\Config\WorkerIds;

/**
 * Provides the worker/datacenter (or machine) IDs that a factory stamps into every ID
 * it generates. Implementations may return a fixed set of values, or claim a set at
 * runtime — factories ask for them on every ID, and never cache the answer.
 */
interface ResolvesIdentities
{
	/**
	 * One value per ID segment in the configuration, in segment order.
	 *
	 * @throws \Glhd\Bits\Exceptions\IdentitySpaceExhausted
	 */
	public function identities(): WorkerIds;
	
	/**
	 * Give up any claim held on these identities so that another process can use them.
	 * Implementations that do not claim anything should treat this as a no-op.
	 */
	public function release(): void;
}
