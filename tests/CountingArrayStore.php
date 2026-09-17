<?php

namespace Glhd\Bits\Tests;

use Illuminate\Cache\ArrayStore;

/**
 * An array store with an atomic-ish `add`, so that Repository::add() takes the same code
 * path it does on Redis, and with counters so that tests can tell a claim (add) apart
 * from a renewal (get + put) and count how many identities were probed.
 */
class CountingArrayStore extends ArrayStore
{
	public int $adds = 0;
	
	public int $gets = 0;
	
	public int $puts = 0;
	
	public int $forgets = 0;
	
	public function add($key, $value, $seconds)
	{
		$this->adds++;
		
		if (null !== parent::get($key)) {
			return false;
		}
		
		return parent::put($key, $value, $seconds);
	}
	
	public function get($key)
	{
		$this->gets++;
		
		return parent::get($key);
	}
	
	public function put($key, $value, $seconds)
	{
		$this->puts++;
		
		return parent::put($key, $value, $seconds);
	}
	
	public function forget($key)
	{
		$this->forgets++;
		
		return parent::forget($key);
	}
	
	public function resetCounts(): void
	{
		$this->adds = $this->gets = $this->puts = $this->forgets = 0;
	}
}
