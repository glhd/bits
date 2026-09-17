<?php

namespace Glhd\Bits\Tests\Unit;

use Glhd\Bits\IdentityResolvers\LinearCongruentialGenerator;
use Glhd\Bits\Tests\TestCase;
use InvalidArgumentException;

class LinearCongruentialGeneratorTest extends TestCase
{
	public function test_it_visits_every_value_exactly_once(): void
	{
		foreach (range(1, 16) as $bits) {
			$size = 2 ** $bits;
			$generator = new LinearCongruentialGenerator(seed: 12345, bits: $bits);
			
			$seen = [];
			
			for ($i = 0; $i < $size; $i++) {
				$seen[$generator->next()] = true;
			}
			
			ksort($seen);
			
			$this->assertEquals(range(0, $size - 1), array_keys($seen), "Expected full coverage of {$size} values.");
		}
	}
	
	public function test_it_repeats_after_a_full_period(): void
	{
		$generator = new LinearCongruentialGenerator(seed: 7, bits: 8);
		
		$first = [];
		$second = [];
		
		for ($i = 0; $i < 256; $i++) {
			$first[] = $generator->next();
		}
		
		for ($i = 0; $i < 256; $i++) {
			$second[] = $generator->next();
		}
		
		$this->assertEquals($first, $second);
		$this->assertEquals(256, $generator->period());
	}
	
	public function test_the_same_seed_produces_the_same_walk(): void
	{
		$one = new LinearCongruentialGenerator(seed: 99, bits: 10);
		$two = new LinearCongruentialGenerator(seed: 99, bits: 10);
		
		for ($i = 0; $i < 25; $i++) {
			$this->assertEquals($one->next(), $two->next());
		}
	}
	
	public function test_it_reads_the_seed_as_three_bit_fields(): void
	{
		// Low 10 bits are the starting point, next 10 the increment, next 10 the multiplier
		$seed = (9 << 20) | (4 << 10) | 3;
		
		$generator = new LinearCongruentialGenerator($seed, 10);
		
		// The increment is forced odd, and the multiplier congruent to 1 modulo 4
		$this->assertEquals(5, $generator->increment());
		$this->assertEquals(9, $generator->multiplier());
		
		// Starting state 3, so the first value is (9 * 3) + 5 = 32
		$this->assertEquals(32, $generator->next());
	}
	
	public function test_it_always_produces_a_full_period_multiplier_and_increment(): void
	{
		foreach (range(1, 12) as $bits) {
			for ($i = 0; $i < 50; $i++) {
				$generator = new LinearCongruentialGenerator(random_int(0, PHP_INT_MAX), $bits);
				
				$this->assertEquals(1, $generator->increment() % 2, 'The increment must be odd.');
				$this->assertEquals(1, $generator->multiplier() % 4, 'The multiplier must be 1 mod 4.');
				
				$seen = [];
				
				for ($n = 0; $n < 2 ** $bits; $n++) {
					$seen[$generator->next()] = true;
				}
				
				$this->assertCount(2 ** $bits, $seen, "A random seed did not cover {$bits} bits.");
			}
		}
	}
	
	public function test_different_seeds_walk_the_space_in_different_orders(): void
	{
		$walks = [];
		
		for ($i = 0; $i < 200; $i++) {
			$generator = new LinearCongruentialGenerator(random_int(0, PHP_INT_MAX), 10);
			
			$walk = [];
			
			for ($n = 0; $n < 5; $n++) {
				$walk[] = $generator->next();
			}
			
			$walks[implode(',', $walk)] = true;
		}
		
		// Not merely a different starting point on one shared ring
		$this->assertGreaterThan(190, count($walks));
	}
	
	public function test_it_handles_negative_seeds(): void
	{
		$generator = new LinearCongruentialGenerator(seed: -1, bits: 10);
		
		$seen = [];
		
		for ($n = 0; $n < 1024; $n++) {
			$seen[$generator->next()] = true;
		}
		
		$this->assertCount(1024, $seen);
	}
	
	public function test_it_rejects_bit_widths_that_would_overflow(): void
	{
		$this->expectException(InvalidArgumentException::class);
		
		new LinearCongruentialGenerator(seed: 1, bits: 61);
	}
	
	public function test_it_rejects_an_empty_space(): void
	{
		$this->expectException(InvalidArgumentException::class);
		
		new LinearCongruentialGenerator(seed: 1, bits: 0);
	}
}
