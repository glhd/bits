<?php

namespace Glhd\Bits\Tests\Unit;

use Glhd\Bits\Config\SnowflakesConfig;
use Glhd\Bits\Config\SonyflakesConfig;
use Glhd\Bits\IdentityResolvers\IdentitySpace;
use Glhd\Bits\Tests\TestCase;
use InvalidArgumentException;

class IdentitySpaceTest extends TestCase
{
	public function test_it_derives_the_snowflake_space_from_the_config(): void
	{
		$space = new IdentitySpace(new SnowflakesConfig());
		
		// The leading pad bit is not part of the ID space, so 5 + 5 bits remain
		$this->assertEquals(10, $space->bits());
		$this->assertEquals(1024, $space->size());
		$this->assertFalse($space->isFixed());
	}
	
	public function test_it_derives_the_sonyflake_space_from_the_config(): void
	{
		$space = new IdentitySpace(new SonyflakesConfig());
		
		$this->assertEquals(16, $space->bits());
		$this->assertEquals(65_536, $space->size());
	}
	
	public function test_a_sonyflake_machine_id_is_pinned_by_its_own_name(): void
	{
		$space = new IdentitySpace(new SonyflakesConfig(), ['machine' => 42]);
		
		$this->assertTrue($space->isFixed());
		$this->assertEquals([42], $space->at(0)->ids);
		$this->assertEquals('machine:16=42', $space->signature());
		
		// Sonyflakes have no datacenter
		$this->assertThrows(
			fn() => new IdentitySpace(new SonyflakesConfig(), ['datacenter' => 42]),
			InvalidArgumentException::class
		);
	}
	
	public function test_it_splits_an_index_across_segments(): void
	{
		$space = new IdentitySpace(new SnowflakesConfig());
		
		$this->assertEquals([0, 0], $space->at(0)->ids);
		$this->assertEquals([0, 1], $space->at(1)->ids);
		$this->assertEquals([0, 31], $space->at(31)->ids);
		$this->assertEquals([1, 0], $space->at(32)->ids);
		$this->assertEquals([1, 1], $space->at(33)->ids);
		$this->assertEquals([31, 31], $space->at(1023)->ids);
	}
	
	public function test_every_index_maps_to_a_distinct_pair(): void
	{
		$space = new IdentitySpace(new SnowflakesConfig());
		
		$seen = [];
		
		for ($index = 0; $index < $space->size(); $index++) {
			$seen[implode('-', $space->at($index)->ids)] = true;
		}
		
		$this->assertCount(1024, $seen);
	}
	
	public function test_it_rejects_an_index_outside_of_the_space(): void
	{
		$space = new IdentitySpace(new SnowflakesConfig());
		
		$this->assertThrows(fn() => $space->at(1024), InvalidArgumentException::class);
		$this->assertThrows(fn() => $space->at(-1), InvalidArgumentException::class);
	}
	
	public function test_a_pinned_segment_is_excluded_from_the_space(): void
	{
		$space = new IdentitySpace(new SnowflakesConfig(), ['datacenter' => 7]);
		
		$this->assertEquals(5, $space->bits());
		$this->assertEquals(32, $space->size());
		
		foreach ($space->candidates(0) as $ids) {
			$this->assertEquals(7, $ids->first());
		}
		
		$this->assertEquals([7, 0], $space->at(0)->ids);
		$this->assertEquals([7, 31], $space->at(31)->ids);
	}
	
	public function test_segments_can_be_pinned_by_position(): void
	{
		$space = new IdentitySpace(new SnowflakesConfig(), [1 => 12]);
		
		$this->assertEquals(32, $space->size());
		$this->assertEquals([0, 12], $space->at(0)->ids);
		$this->assertEquals([31, 12], $space->at(31)->ids);
	}
	
	public function test_a_fully_pinned_space_has_nothing_to_claim(): void
	{
		$space = new IdentitySpace(new SnowflakesConfig(), ['datacenter' => 3, 'worker' => 9]);
		
		$this->assertTrue($space->isFixed());
		$this->assertEquals(1, $space->size());
		$this->assertEquals([3, 9], $space->at(0)->ids);
		$this->assertEquals([[3, 9]], array_map(fn($ids) => $ids->ids, iterator_to_array($space->candidates(0))));
	}
	
	public function test_a_null_pin_leaves_the_segment_claimable(): void
	{
		$space = new IdentitySpace(new SnowflakesConfig(), ['datacenter' => null, 'worker' => null]);
		
		$this->assertEquals(1024, $space->size());
	}
	
	public function test_it_rejects_unknown_and_out_of_range_pins(): void
	{
		$this->assertThrows(
			fn() => new IdentitySpace(new SnowflakesConfig(), ['nope' => 1]),
			InvalidArgumentException::class
		);
		
		// 'pad' is not an ID segment, so it cannot be pinned
		$this->assertThrows(
			fn() => new IdentitySpace(new SnowflakesConfig(), ['pad' => 0]),
			InvalidArgumentException::class
		);
		
		$this->assertThrows(
			fn() => new IdentitySpace(new SnowflakesConfig(), ['worker' => 32]),
			InvalidArgumentException::class
		);
		
		$this->assertThrows(
			fn() => new IdentitySpace(new SnowflakesConfig(), ['worker' => -1]),
			InvalidArgumentException::class
		);
	}
	
	public function test_the_candidate_walk_covers_the_whole_space_exactly_once(): void
	{
		$space = new IdentitySpace(new SnowflakesConfig());
		
		foreach ([0, 1, 511, 1023] as $seed) {
			$seen = [];
			
			foreach ($space->indexes($seed) as $index) {
				$seen[$index] = true;
			}
			
			ksort($seen);
			
			$this->assertEquals(range(0, 1023), array_keys($seen), "Seed {$seed} did not cover the space.");
		}
	}
	
	public function test_its_signature_describes_the_space(): void
	{
		$this->assertEquals(
			'datacenter:5,worker:5',
			(new IdentitySpace(new SnowflakesConfig()))->signature()
		);
		
		$this->assertEquals(
			'datacenter:5=2,worker:5',
			(new IdentitySpace(new SnowflakesConfig(), ['datacenter' => 2]))->signature()
		);
		
		$this->assertEquals(
			'machine:16',
			(new IdentitySpace(new SonyflakesConfig()))->signature()
		);
	}
}
