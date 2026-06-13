# Progress — foundations-money-i18n-flags

## Codebase Patterns
(consolidated reusable patterns — read first each iteration)

- **Fixed-set value type → string-backed enum.** When a VO's domain is a closed set whose members carry derived data, realise it as a `string`-backed enum (the cases ARE the single-source-of-truth set; value-equality and `from`/`tryFrom` are native). Per-case data via an **exhaustive `match`** (no `default` → PHPStan-max-clean, and a new case fails loudly until handled). Used by `Currency` (code→`minorUnitExponent()`); reuse for `SupportedLocale` (2.1).
- **Fail-closed VO factory.** `public static function of(string $x): self { return self::tryFrom($x) ?? throw new InvalidArgumentException("…names the valid set + the one-line fix…"); }` — the PHP 8 throw-expression keeps it a one-liner. Exact match, no case-folding (a non-canonical input is a caller bug → reject). Money/FX/locale VOs all follow this.
- **No `declare(strict_types=1)`** anywhere in `app/Platform` — match the house style (ActorRole, DeliveryStatus, NotInTransactionException all omit it). Pint enforces the rest.
- **Exception choice:** SPL `InvalidArgumentException` for "bad argument to a VO factory" with an intentful message; custom `Runtime`/SPL subclass (cf. `NotInTransactionException`) only when a named domain guard recurs across call-sites.
- **Verbatim set pin** (mirror `tests/Unit/Platform/EnumsTest.php`): loop `$values[$case->name] = $case->value;` then `expect($values)->toBe([...])` — order-sensitive, so any case/value drift fails first. Use for every closed enum set.
- **Test placement:** pure VO/enum tests → `tests/Unit/Platform/<Sub>/…Test.php` (Pest binds the Laravel `TestCase` only `->in('Feature')`, so Unit tests need no app/DB — fine for pure logic). DB-touching tests (casts: `MoneyCast` 1.5, `TranslatableTextCast` 2.3, Pennant 3.x) live under `tests/Feature/Platform/…` and opt in per-file with `uses(RefreshDatabase::class)` (Pest.php leaves `RefreshDatabase` commented out globally).

---

## [2026-06-13 09:01] — 1.1 Currency
- Implemented `App\Platform\Money\Currency` — a `string`-backed enum, the launch ISO 4217 set fixed by DEC-037 at exactly five: `EUR`/`USD`/`GBP`/`CHF`/`JPY` (case name == ISO code == backing value). `minorUnitExponent()` via exhaustive `match` (JPY→0, cent currencies→2); `base()` → EUR (single accessor, no hardcoded `Currency::EUR` at call-sites); `of(string)` fail-closed factory (`tryFrom ?? throw InvalidArgumentException`) naming the supported set + the one-line add-a-case fix.
- **Decision recorded in-class:** `of()` throws SPL `InvalidArgumentException` (not the native enum `ValueError`) for a debuggable money-discipline message; matching is exact (no case-folding) so `'eur'` is rejected too.
- Files changed: `app/Platform/Money/Currency.php` (new), `tests/Unit/Platform/Money/CurrencyTest.php` (new, 7 tests / 13 assertions).
- Quality loop: **green** — pint clean · CurrencyTest 7/7 · full suite **158/158** (was 151, +7) · phpstan **0** @ max · pint --test clean · `openspec validate --strict` green. No `composer.json`/`.lock` churn (owned code, no dep).
- **Acceptance:** all four money/Currency delta scenarios covered (JPY exp 0 · EUR/USD/GBP/CHF exp 2 · EUR base · unsupported code throws), plus the exactly-five set pin and happy/failure `of()` paths.
- **Learnings for future iterations:**
  - The Currency enum is the template for the rest of Section 1 — `Money` (1.2) composes `int $minorUnits` + `Currency`; use `Currency::of($code)` to rehydrate from a column/payload and `$currency->value` to serialise the code (no separate `code()` accessor was added — kept 1.1 minimal; add one in 1.2 only if a call-site actually wants the alias).
  - `minorUnitExponent()` is the spec-faithful accessor name `Money` arithmetic/formatting will lean on.
  - The boundary arch test needed no touch (design D1 holds — `App\Platform\Money` is prefix-covered); the new namespace imported no `App\Modules` code.
---
