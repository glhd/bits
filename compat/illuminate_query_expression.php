<?php

namespace Illuminate\Contracts\Database\Query {
	interface Expression
	{
		public function getValue(\Illuminate\Database\Grammar $grammar);
	}
}


