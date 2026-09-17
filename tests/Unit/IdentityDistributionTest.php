<?php

namespace Glhd\Bits\Tests\Unit;

use Glhd\Bits\Config\SnowflakesConfig;
use Glhd\Bits\IdentityResolvers\CacheIdentityResolver;
use Glhd\Bits\IdentityResolvers\IdentitySpace;
use Glhd\Bits\Tests\CountingArrayStore;
use Glhd\Bits\Tests\TestCase;
use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Date;

/**
 * Settled decision 4: processes have to spread their claims over the ID space. The
 * failure mode to avoid is every process trying (0, 0), all but one conflicting, then
 * all of them trying the same second candidate, and so on.
 */
class IdentityDistributionTest extends TestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		
		Date::setTestNow(now());
	}
	
	public function test_first_choices_are_spread_across_the_whole_space(): void
	{
		$space = new IdentitySpace(new SnowflakesConfig());
		$counts = array_fill(0, 1024, 0);
		$trials = 20_480;
		
		for ($i = 0; $i < $trials; $i++) {
			$counts[$space->indexes(random_int(0, PHP_INT_MAX))->current()]++;
		}
		
		// Every identity in the space gets picked first at least sometimes
		$this->assertCount(0, array_keys($counts, 0), 'Some identities are never chosen first.');
		
		// And none is favored: 20 picks each on average, so allow a wide band, but
		// nothing close to the clustering this is testing against
		$this->assertLessThan(60, max($counts), 'One identity is chosen first far too often.');
		$this->assertGreaterThan(2, min($counts));
	}
	
	public function test_a_different_seed_means_a_different_walk(): void
	{
		$space = new IdentitySpace(new SnowflakesConfig());
		
		$walks = [];
		
		for ($i = 0; $i < 500; $i++) {
			$walk = [];
			
			foreach ($space->indexes(random_int(0, PHP_INT_MAX)) as $index) {
				$walk[] = $index;
				
				if (5 === count($walk)) {
					break;
				}
			}
			
			$walks[implode(',', $walk)] = true;
		}
		
		// Different processes walk in different orders, not just from different
		// starting points on one shared order
		$this->assertGreaterThan(480, count($walks));
	}
	
	public function test_the_same_seed_means_the_same_walk(): void
	{
		$space = new IdentitySpace(new SnowflakesConfig());
		
		$one = [];
		$two = [];
		
		foreach ($space->indexes(404) as $index) {
			$one[] = $index;
		}
		
		foreach ($space->indexes(404) as $index) {
			$two[] = $index;
		}
		
		$this->assertEquals($one, $two);
		$this->assertCount(1024, array_unique($one));
	}
	
	public function test_filling_the_space_costs_far_less_than_a_fixed_scan_order(): void
	{
		$store = new CountingArrayStore();
		$cache = new Repository($store);
		
		for ($process = 0; $process < 1024; $process++) {
			CacheIdentityResolver::forgetClaims();
			
			$this->resolver($cache)->identities();
		}
		
		// Filling all 1024 identities by scanning from index 0 every time costs
		// sum(1..1024) = 524,800 probes. Seeded walks measure at about 6,800 on
		// average, with 8,600 the worst of 30 runs.
		$this->assertGreaterThanOrEqual(1024, $store->adds);
		$this->assertLessThan(12_000, $store->adds);
	}
	
	public function test_early_processes_almost_never_conflict(): void
	{
		$store = new CountingArrayStore();
		$cache = new Repository($store);
		
		// Fill a tenth of the space
		for ($process = 0; $process < 102; $process++) {
			CacheIdentityResolver::forgetClaims();
			
			$this->resolver($cache)->identities();
		}
		
		// With at most 10% of a 1024-identity space taken, 102 claims should need
		// barely more than 102 probes. Lockstep probing would need about 5,200.
		$this->assertLessThan(130, $store->adds);
	}
	
	public function test_half_of_the_space_fills_at_roughly_one_probe_each(): void
	{
		$store = new CountingArrayStore();
		$cache = new Repository($store);
		
		for ($process = 0; $process < 512; $process++) {
			CacheIdentityResolver::forgetClaims();
			
			$this->resolver($cache)->identities();
		}
		
		// Measured at about 710 probes for 512 claims. A fixed scan order costs 131,328.
		$this->assertLessThan(900, $store->adds);
	}
	
	protected function resolver(Repository $cache): CacheIdentityResolver
	{
		return new CacheIdentityResolver(
			cache: $cache,
			space: new IdentitySpace(new SnowflakesConfig()),
			scope: 'snowflake',
		);
	}
}
