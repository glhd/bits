<?php

namespace Glhd\Bits\Tests\Unit;

use Glhd\Bits\Config\SnowflakesConfig;
use Glhd\Bits\Config\SonyflakesConfig;
use Glhd\Bits\Contracts\Configuration;
use Glhd\Bits\Exceptions\IdentitySpaceExhausted;
use Glhd\Bits\IdentityResolvers\CacheIdentityResolver;
use Glhd\Bits\IdentityResolvers\IdentitySpace;
use Glhd\Bits\Tests\CountingArrayStore;
use Glhd\Bits\Tests\TestCase;
use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Date;

/**
 * Real concurrency is out of reach in a unit test, so these stand in a group of
 * independent resolvers over one shared cache for a group of processes over one shared
 * Redis. CacheIdentityResolver tracks its claim per-process, so forgetting that claim
 * before each call is what makes each call behave like a freshly booted process.
 *
 * What this pins down is that the claim logic never hands the same identity to two
 * callers, and that claims are spread over the space rather than piling onto the same
 * candidate. Whether the underlying write is atomic is the cache store's job, and Redis
 * answers that with SET NX.
 */
class ConcurrentIdentityClaimTest extends TestCase
{
	protected CountingArrayStore $store;
	
	protected Repository $cache;
	
	protected function setUp(): void
	{
		parent::setUp();
		
		Date::setTestNow(now());
		
		$this->store = new CountingArrayStore();
		$this->cache = new Repository($this->store);
	}
	
	public function test_a_thousand_processes_starting_at_once_all_get_a_different_identity(): void
	{
		$claimed = [];
		
		for ($process = 0; $process < 1024; $process++) {
			$ids = $this->claimAsNewProcess();
			
			$claimed[implode('-', $ids->ids)] = true;
		}
		
		$this->assertCount(1024, $claimed, 'Two processes were given the same identity.');
	}
	
	public function test_the_process_that_cannot_find_a_free_identity_is_told_so(): void
	{
		for ($process = 0; $process < 1024; $process++) {
			$this->claimAsNewProcess();
		}
		
		$this->assertThrows(fn() => $this->claimAsNewProcess(), IdentitySpaceExhausted::class);
	}
	
	public function test_releasing_an_identity_frees_it_for_the_next_process(): void
	{
		$last = null;
		
		for ($process = 0; $process < 1024; $process++) {
			CacheIdentityResolver::forgetClaims();
			
			$last = $this->resolver();
			$last->identities();
		}
		
		// The last process to start shuts down, handing its identity back
		$freed = $last->claimed();
		$this->assertNotNull($freed);
		
		$last->release();
		
		// That is now the only free identity in the space, so the next process to start
		// has to be given exactly it
		$this->assertEquals($freed->ids, $this->claimAsNewProcess()->ids);
		
		// And the one after that has nothing left to take
		$this->assertThrows(fn() => $this->claimAsNewProcess(), IdentitySpaceExhausted::class);
	}
	
	public function test_processes_do_not_herd_onto_the_same_candidate(): void
	{
		$this->store->resetCounts();
		
		for ($process = 0; $process < 1024; $process++) {
			$this->claimAsNewProcess();
		}
		
		// Filling a 1024-slot space by scanning from the same starting point every time
		// costs ~524,000 probes. Starting each process somewhere else costs ~6,800.
		$this->assertLessThan(1024 * 20, $this->store->adds, 'Processes are probing in lockstep.');
		
		// Sanity check on the lower bound: 1024 claims cannot cost fewer than 1024 probes
		$this->assertGreaterThanOrEqual(1024, $this->store->adds);
	}
	
	public function test_snowflake_and_sonyflake_identities_do_not_collide(): void
	{
		$snowflake = $this->claimAsNewProcess(new SnowflakesConfig(), 'snowflake');
		$sonyflake = $this->claimAsNewProcess(new SonyflakesConfig(), 'sonyflake');
		
		$this->assertCount(2, $snowflake->ids);
		$this->assertCount(1, $sonyflake->ids);
		
		// Both formats hold a claim at the same time, under their own scope
		$this->assertNotNull($this->cache->get('glhd-bits-id:snowflake:'.implode('-', $snowflake->ids)));
		$this->assertNotNull($this->cache->get('glhd-bits-id:sonyflake:'.implode('-', $sonyflake->ids)));
	}
	
	public function test_a_wide_space_absorbs_a_large_burst_of_processes(): void
	{
		$claimed = [];
		
		for ($process = 0; $process < 2_000; $process++) {
			$ids = $this->claimAsNewProcess(new SonyflakesConfig(), 'sonyflake');
			
			$claimed[$ids->first()] = true;
		}
		
		$this->assertCount(2_000, $claimed);
		
		// 2,000 claims into a 65,536-slot space should almost never collide
		$this->assertLessThan(2_100, $this->store->adds);
	}
	
	protected function claimAsNewProcess(?Configuration $config = null, string $scope = 'snowflake')
	{
		CacheIdentityResolver::forgetClaims();
		
		return $this->resolver($config, $scope)->identities();
	}
	
	protected function resolver(?Configuration $config = null, string $scope = 'snowflake'): CacheIdentityResolver
	{
		return new CacheIdentityResolver(
			cache: $this->cache,
			space: new IdentitySpace($config ?? new SnowflakesConfig()),
			scope: $scope,
		);
	}
}
