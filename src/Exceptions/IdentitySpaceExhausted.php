<?php

namespace Glhd\Bits\Exceptions;

use RuntimeException;

class IdentitySpaceExhausted extends RuntimeException
{
	public static function for(string $scope, int $size, int $probes): static
	{
		return new static(
			"Unable to claim a unique '{$scope}' identity. Probed {$probes} of {$size} available "
			.'combinations without finding a free one. Either more processes are generating IDs '
			.'than the ID segments of this configuration can represent, or previously claimed '
			.'identities have not expired yet.'
		);
	}
}
