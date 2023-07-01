<?php

namespace Glhd\Bits\Database;

use Glhd\Bits\Snowflake;

trait HasSnowflakePrimaryKey
{
	protected function initializeHasSnowflakePrimaryKey(): void
	{
		$this->attributes[$this->getKeyName()] = Snowflake::make()->id();
	}
}
