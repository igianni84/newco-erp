<?php

namespace App\Platform\Events;

/**
 * The result of one {@see InlineDeliveryExecutor::attempt()} over a single delivery row, tallied into
 * the sweep's run summary (substrate-hardening C3; design D3). Three outcomes, because under
 * inline-vs-sweep concurrency an attempt may neither deliver nor fail: a sibling runner that already
 * completed the row leaves nothing to do — attempt()'s locked status re-check, or recordFailure()'s
 * pending-guarded write, matches no work (C1). A `Skipped` attempt is counted as neither delivered
 * nor failed, so the summary reflects only the deliveries this run actually ran.
 */
enum AttemptOutcome
{
    case Delivered;
    case Failed;
    case Skipped;
}
