<?php

namespace App\Modules\Catalog\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * VariantCaseWhitelistEntry — one admitted (Product Variant, Format, Case Configuration) triple of the
 * Layer-1 possible-case-configurations whitelist (catalog-module-0-completeness-sweep, design D6;
 * product-catalog — Requirement: Layer-1 Case-Configuration Whitelist; Module 0 PRD § 3.3 + § 7.1).
 *
 * The whitelist is scoped per (Variant, FORMAT) PAIR: "this product, in this format, can in principle be
 * packaged in these forms". A pair with ZERO rows is PERMISSIVE (§ 7.1's default) — presence narrows,
 * absence admits — so there is no boolean here and no "admit everything" row.
 *
 * It carries NO breakability flag (BR-RefData-2 / AC-0-XM-11) and must never be read as one: Layer 1
 * catalogs POSSIBILITY only. A whitelisted Case Configuration still defaults to breakable unless Layer 2
 * (Module A) or Layer 3 (Module S) declares otherwise; the effective rule is computed downstream, and no
 * module can read an `is_breakable` flag from PIM because PIM exposes none.
 *
 * Persistence-only by design, like the sibling spine models: the `SetVariantCaseWhitelist` action is the
 * sole writer — it replaces a pair's admitted set inside one transaction and records the audit row against
 * the parent Variant (`catalog.product_variant.whitelist_updated`), never a domain event and never a
 * `version` bump — so `$guarded = []` carries no mass-assignment-from-request risk. The entry has no
 * lifecycle of its own: it rides its Variant's.
 *
 * All three references are WITHIN the Catalog module (invariant 10 is not in play). The reverse relation
 * lives on the Variant ({@see ProductVariant::caseWhitelistEntries()}); this row deliberately exposes no
 * `belongsTo` accessors — nothing reads an entry as an object graph, only as a set of ids.
 *
 * @property int $id
 * @property int $product_variant_id
 * @property int $format_id
 * @property int $case_configuration_id
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 */
class VariantCaseWhitelistEntry extends Model
{
    protected $table = 'catalog_variant_case_whitelists';

    /**
     * The maintenance action is the only writer; it builds the row set internally from validated ids, so
     * there is no mass-assignment from request input to guard (mirrors the sibling spine models).
     *
     * @var list<string>
     */
    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'product_variant_id' => 'integer',
            'format_id' => 'integer',
            'case_configuration_id' => 'integer',
        ];
    }
}
