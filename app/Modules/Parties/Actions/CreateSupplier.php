<?php

namespace App\Modules\Parties\Actions;

use App\Modules\Parties\Enums\PartyType;
use App\Modules\Parties\Models\Supplier;
use Illuminate\Support\Facades\DB;

/**
 * Creates a minimal Supplier carrying the immutable party-type marker `supplier` (parties-core, design
 * D1/D7/D10; party-registry — Requirement: Supplier — Minimal Party Subtype).
 *
 * Two deliberate spec-faithful asymmetries versus the other Create* actions:
 *   - **No event** — the PRD § 15 event catalog names no Supplier event, so this action records none (design
 *     D7). Inventing a `SupplierCreated` would breach spec fidelity; the action therefore needs neither the
 *     platform domain-event recorder nor the actor context that the other Create* actions inject.
 *   - **No auto-cross-create** — creating a Supplier creates only a Supplier, never a Producer (BR-K-Producer-3,
 *     design D10); the Supplier↔Producer link is Module D's `SupplierProducerLink`, not modelled here.
 *
 * The single insert is still wrapped in one {@see DB::transaction} to keep the creation seam consistent with
 * the rest of the spine (and the place the deferred lifecycle change extends). The `party_type` marker is
 * fixed to `supplier` here — it is set at creation and has no mutation surface (BR-K-Identity-5).
 */
class CreateSupplier
{
    public function handle(string $legalName): Supplier
    {
        return DB::transaction(fn (): Supplier => Supplier::create([
            'legal_name' => $legalName,
            'party_type' => PartyType::Supplier,
        ]));
    }
}
