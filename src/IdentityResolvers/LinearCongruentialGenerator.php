<?php

namespace Glhd\Bits\IdentityResolvers;

use InvalidArgumentException;

/**
 * A linear congruential generator over a power-of-two modulus, used to walk an identity
 * space in an order that is random across processes but fixed within one.
 *
 * With a modulus of 2**bits, the Hull-Dobell theorem gives a full period — every value
 * in [0, 2**bits) visited exactly once before the sequence repeats — when the increment
 * is odd and the multiplier is congruent to 1 modulo 4. Both are read out of the seed
 * and forced into those families, so every seed produces a valid full-period walk.
 *
 * The seed is read as three bit fields: the starting point, then the increment, then the
 * multiplier. Giving each process its own multiplier and increment, rather than only its
 * own starting point on one shared ring, is what keeps probing cheap once the space
 * starts filling up. Filling a 1024-identity space completely costs about 6,800 probes
 * this way, against about 20,000 when every process walks the same ring, and 524,800
 * when every process scans from zero.
 *
 * Above 21 bits the seed runs out of room for the upper fields and they read as zero,
 * leaving a multiplier of 1 and an increment of 1: still a full period, just the same
 * ring for everyone. A space that large is nowhere near filling up, so it does not
 * matter there.
 */
class LinearCongruentialGenerator
{
	/**
	 * Above 60 bits, multiplier * state overflows a 64-bit integer. No realistic ID
	 * configuration comes close, but fail loudly rather than silently wrap.
	 */
	protected const MAX_BITS = 60;
	
	protected int $modulus;
	
	protected int $multiplier;
	
	protected int $increment;
	
	protected int $state;
	
	public function __construct(int $seed, protected int $bits)
	{
		if ($bits < 1 || $bits > static::MAX_BITS) {
			throw new InvalidArgumentException(
				sprintf('A linear congruential generator needs between 1 and %d bits (got %d).', static::MAX_BITS, $bits)
			);
		}
		
		$this->modulus = 2 ** $bits;
		
		$mask = $this->modulus - 1;
		
		// PHP's modulo and right shift both keep the sign of the left operand, so work
		// from a non-negative seed
		$seed &= PHP_INT_MAX;
		
		$this->state = $seed & $mask;
		
		// The increment has to be odd
		$this->increment = (($seed >> $bits) & $mask) | 1;
		
		// The multiplier has to be congruent to 1 modulo 4
		$this->multiplier = ((($seed >> (2 * $bits)) & $mask) & ~3) | 1;
	}
	
	public function next(): int
	{
		return $this->state = (($this->multiplier * $this->state) + $this->increment) % $this->modulus;
	}
	
	public function period(): int
	{
		return $this->modulus;
	}
	
	public function multiplier(): int
	{
		return $this->multiplier;
	}
	
	public function increment(): int
	{
		return $this->increment;
	}
}
