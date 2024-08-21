<?php

return [
	/*
	|--------------------------------------------------------------------------
	| Default Format
	|--------------------------------------------------------------------------
	|
	| There are two default formats available: 'snowflake' or 'sonyflake'
	|
	| Each have their own trade-offs. See https://github.com/glhd/bits
	| for details about each format.
	*/
	
	'format' => 'snowflake',
	
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
	| AWS Lambda (and Laravel Vapor)
	|--------------------------------------------------------------------------
	|
	| Snowflakes/etc rely on unique datacenter and worker IDs to operate. 
	| Because AWS lambdas are different machines each time, it's impossible 
	| to assign each a unique worker/device ID.
	|
	| Bits addresses this with a special lambda ID resolver, which assigns
	| and locks IDs via your cache.
	| 
	| Options: true, false, or "autodetect"
	*/
	
	'lambda' => env('BITS_LAMBDA', 'autodetect'),
	
	/*
	|--------------------------------------------------------------------------
	| Worker ID
	|--------------------------------------------------------------------------
	|
	| For Snowflakes, you can have up to 31 workers per datacenter. For
	| Sonyflakes, you can have up to 65535 total workers across your entire
	| deployment. If you deploy to multiple hosts, be sure to give each a
	| unique worker ID to avoid concurrency issues.
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
	|
	| Sonyflakes do not use datacenter IDs (and instead use larger worker IDs).
	*/
	
	'datacenter_id' => env('BITS_DATACENTER_ID'),
];
