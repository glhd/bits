<?php

namespace Glhd\Bits\Support\Livewire;

use Glhd\Bits\Bits;
use Livewire\Mechanisms\HandleComponents\Synthesizers\Synth;

class BitsSynth extends Synth
{
	public static string $key = 'bits';
	
	public static function match($target)
	{
		return $target instanceof Bits;
	}
	
	/** @var Bits $target */
	public function dehydrate($target)
	{
		return [$target->jsonSerialize(), ['class' => $target::class]];
	}
	
	/**
	 * @param string $value
	 * @param array{ class: class-string<Bits> } $meta
	 */
	public function hydrate($value, $meta)
	{
		return $meta['class']::fromId($value);
	}
}
