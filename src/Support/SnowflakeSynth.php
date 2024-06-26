<?php

namespace Glhd\Bits\Support;

use Glhd\Bits\Snowflake;
use Livewire\Mechanisms\HandleComponents\Synthesizers\Synth;

class SnowflakeSynth extends Synth
{
	public static $key = 'snowflake';

	public static function match($target)
	{
		return $target instanceof Snowflake;
	}

	public function dehydrate($target)
	{
		return [$target->id(), []];
	}

	public function hydrate($value)
	{
		return Snowflake::fromId($value);
	}
}
