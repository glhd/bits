<?php

namespace Glhd\Bits\Tests\Unit;

use Glhd\Bits\SequenceResolvers\CacheSequenceResolver;
use Glhd\Bits\Tests\TestCase;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Date;

class CacheSequenceResolverTest extends TestCase
{
	public function test_it_increments_sequences(): void
	{
		$resolver = new CacheSequenceResolver(new Repository(new ArrayStore()));
		
		Date::setTestNow(now());
		
		for ($i = 0; $i < 100; $i++) {
			$this->assertEquals($i, $resolver->next(now()->getPreciseTimestamp(3)));
		}
		
		Date::setTestNow(now()->addMinute());
		
		for ($i = 0; $i < 100; $i++) {
			$this->assertEquals($i, $resolver->next(now()->getPreciseTimestamp(3)));
		}
	}
}
