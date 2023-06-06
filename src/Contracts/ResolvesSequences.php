<?php

namespace Glhd\Bits\Contracts;

interface ResolvesSequences
{
    public function next(int $timestamp): int;
}
