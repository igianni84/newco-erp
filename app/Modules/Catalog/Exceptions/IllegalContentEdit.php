<?php

namespace App\Modules\Catalog\Exceptions;

use App\Modules\Catalog\Enums\LifecycleState;
use RuntimeException;

/**
 * Raised when a catalog CONTENT EDIT is attempted on an entity whose lifecycle state does not admit edits
 * (catalog-module-0-completeness-sweep, design D2/D3; product-catalog — Requirement: In-Place Versioned
 * Identity Edits).
 *
 * An edit is NOT a lifecycle transition (design D3 — that is why the shared `CatalogContentEdit` mechanic sits
 * beside `LifecycleTransition` rather than inside it), so it carries its own state guard and its own rejection:
 * content is editable in `draft`, `reviewed` and `active` — the FSM has no `active → reviewed` edge, so an
 * `active` entity's correction stays `active` and merely re-arms review — and rejected on a `retired` entity,
 * whose remedy is the `retired → reviewed` reopen. The guard is asserted against the transaction-LOCKED re-read,
 * so an edit decided on a stale snapshot is rejected and the transaction rolls back: the row, its `version`, the
 * audit trail and the event log are left unchanged.
 *
 * ONE parameterized exception serves every edit surface (Product Master identity, Composite SKU composition,
 * Variant enrichment and whitelist maintenance): the state guard is uniform across them, so the entity name is a
 * factory parameter, exactly as in the sibling `IllegalLifecycleTransition`. The reason is localized through
 * Laravel's translator (CLAUDE.md invariant 12): the English baseline lives in the `edit` group of
 * `lang/en/catalog.php` (key `cannot_edit`), with `:state` and `:entity` placeholders. The offending state token
 * (`$from->value`) is a business enum value and the entity name (`$entity`, e.g. `ProductMaster`) an entity-type
 * label — NEITHER is PII — so both are interpolated to make the reason self-documenting. `(string)` coerces the
 * translator return (typed `mixed` by Larastan) to the RuntimeException message contract.
 */
class IllegalContentEdit extends RuntimeException
{
    public static function cannotEdit(LifecycleState $from, string $entity): self
    {
        return new self((string) __('catalog.edit.cannot_edit', [
            'state' => $from->value,
            'entity' => $entity,
        ]));
    }
}
