<?php

namespace App\Support\Time;

use Carbon\CarbonImmutable;

/**
 * Injectable clock (R-CODE-08). Domain logic depends on this, never on now() /
 * Carbon::now() directly, so time can be frozen/offset in tests.
 */
interface Clock
{
    /** Current instant in UTC (all timestamps are stored UTC — DB Arch §0). */
    public function now(): CarbonImmutable;
}
