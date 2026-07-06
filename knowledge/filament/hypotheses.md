# Filament — Hypotheses (test when possible; Confirmations: N/3)

> An observation becomes a hypothesis once it has a plausible mechanism. Three dated confirmations in DIFFERENT changes promote it to `rules.md`; a contradiction demotes it back here. Mechanics: `.claude/CLAUDE.md` → Knowledge System.

## A Filament-5 display/form expression the runtime accepts can still red PHPStan-max under Larastan — verify shapes against `phpstan analyse`, not just a green suite

**Hypothesis.** A recurring family in the operator-console build-out: the runtime chains an expression fine but Larastan (PHPStan max) rejects it, so a 100%-green suite is **not** proof the code type-checks. The known Filament-5 shapes and their fixes:
1. **Nullable `belongsTo` read in a display column/entry.** The only clean shape is a **local + null-check on the RELATION** — `$x = $record->rel; return $x === null ? … : $x->attr;`. Larastan rejects `$record->rel?->attr` (`nullsafe.neverNull` — the nullsafe operand is typed off the non-null `BelongsTo<T>` generic) AND FK-column narrowing then `$record->rel->attr` (`property.nonObject` — narrowing `rel_id` doesn't narrow the `rel` relation).
2. **A `->numeric()` field dehydrates as a FLOAT.** A `createViaAction`/payload guard must narrow it with `is_numeric()` (+ an explicit `=== ''`/`is_null` arm), never `is_string`/`is_int` — a float silently fails those. The catalog `CreateProductVariant` (`vintage_year`) is the reference shape.
3. **The form-component base is `Filament\Schemas\Components\Component`, NOT `Filament\Forms\Components\Component`** (the latter does not exist in Filament 5 — forms unified under the schemas package). A bare `Component` docblock/param lets Pint auto-import the wrong (non-existent) class → `class.notFound`. Verify the FQCN in `vendor/` first.
4. **`Filament\Actions\Action` `->action(fn (Model, array $data))` — the `data` injection is ALWAYS an array** (`[]` for a form-less action), safe to type `array`.
5. **A multi-method Pest `expect()` chain degrades to `mixed`** (the `->toContain()->toContain()->not->…` shape loses the `Expectation<T>` generic) — one assertion per `expect()`; for membership prefer `expect(in_array($x, $list, true))->toBeTrue()`. (Cross-links the `testing`/`laravel` Larastan-max lessons — same root cause, Filament surface.)

**Confirmations: 1/3** (rule-grade if it recurs in a 5th–6th console; already dense within the existing 4 changes but tracked here as one consolidated shape).
- 2026-06-20 → 2026-06-21 `operator-console-{catalog-master,catalog-spine,parties-producer,parties-supply-side}` — every sub-trap surfaced and was fixed across the four console changes (see `lessons.md` 2026-06-20/2026-06-21 entries for the per-trap detail).

**Applies to.** Every OperatorPanel Filament resource/page/entry with a nullable relation, a numeric input, a form-component type-ref, or a non-trivial Pest expectation over its output. Confirm-or-refine on the next module console (A/D/S/…).
