<?php

namespace Glhd\Bits\Database;

use Glhd\Bits\Bits;
use Glhd\Bits\Contracts\MakesBits;

/**
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait HasSnowflakes
{
	protected function initializeHasSnowflakes(): void
	{
		$this->mergeCasts(array_fill_keys($this->uniqueIds(), Bits::class));

		if (property_exists($this, 'usesUniqueIds')) {
			$this->usesUniqueIds = true;
			return;
		}
		
		$this->setAttribute($this->getKeyName(), $this->newUniqueId());
	}
	
	public function uniqueIds()
	{
		return [$this->getKeyName()];
	}
	
	public function newUniqueId()
	{
		return app(MakesBits::class)->make()->id();
	}
	
	public function getIncrementing()
	{
		if (in_array($this->getKeyName(), $this->uniqueIds())) {
			return false;
		}
		
		return $this->incrementing;
	}
}
