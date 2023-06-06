<?php

return [
	/*
	|--------------------------------------------------------------------------
	| Epoch
	|--------------------------------------------------------------------------
	|
	| Each snowflake is generated relative to an epoch timestamp (much like
	| unix timestamps). This allows them to be precise and still fit in
	| a 64-bit integer. This should be a date in the past, but as recent
	| as possible.
	|
	| PLEASE NOTE: If you change this after you start generating snowflakes,
	|              you may run into collisions!
	|
	*/
	
	'epoch' => env('BITS_EPOCH', '2023-01-01'),
	
	/*
	|--------------------------------------------------------------------------
	| Worker ID
	|--------------------------------------------------------------------------
	|
	| You can have up to 31 workers in each datacenter. If you deploy to
	| multiple hosts, be sure to give each a unique worker ID to avoid
	| concurrency issues.
	*/
	
	'worker_id' => env('BITS_WORKER_ID'),
	
	/*
	|--------------------------------------------------------------------------
	| Datacenter ID
	|--------------------------------------------------------------------------
	|
	| You can have up to 31 datacenters with the default snowflake
	| configuration. If you are deploying to multiple datacenters, be sure to
	| set this to a unique value in each.
	*/
	
	'datacenter_id' => env('BITS_DATACENTER_ID'),
];
