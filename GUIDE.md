# GUIDE.md — Manuale operativo: da zero all'ERP completo

> Questa è la guida per **chi guida la macchina** (Giovanni). Il `README.md` spiega *cos'è* il sistema; questa guida spiega *cosa fare tu*, nell'ordine giusto, con i comandi e i prompt esatti. È un documento vivo: quando il processo cambia, aggiornala.

---

## 0. Il modello mentale (30 secondi)

- **Tu decidi COSA si costruisce**: scrivi le ADR ai gate, prepari e approvi i change, fai review, merge e archive.
- **Il loop decide COME**: implementa un task alla volta, con test obbligatori, dentro i binari (invarianti di `CLAUDE.md`, guardrail git, `openspec validate`).
- L'unità di lavoro è il **change OpenSpec**. Tutto l'ERP è una sequenza di change, ognuno con lo stesso ciclo di vita:

```
preparare (/spec-to-change) → approvare (APPROVED) → loop (./ralph.sh)
→ review + merge → verifica semantica → archive → push
```

**Dove avviene cosa:**

| Attività | Dove |
|---|---|
| Sessioni ADR, /spec-to-change, verifica, debug | Finestra Claude Code in `newco-erp` (una sessione = UNO scopo, poi `/clear` o nuova finestra) |
| Il loop ralph | **Terminale puro** (mai dentro una sessione Claude: il loop lancia le sue istanze) |
| Review di artefatti e diff | Dove preferisci (editor, GitHub) |

---

## 1. FASE 0 — Smoke test della macchina ← **SEI QUI**

Obiettivo: vedere il ciclo completo funzionare su un change a basso rischio (`bootstrap-laravel-app`, 9 task) prima dei moduli veri. Tempo: 1–2 ore, in gran parte di attesa.

**Step 1 — Leggi il change (10 min).** Come fosse il piano di un collega:

```bash
cd /Users/igianni84/Downloads/newco-erp
cat openspec/changes/bootstrap-laravel-app/proposal.md
cat openspec/changes/bootstrap-laravel-app/design.md     # occhio a "Install procedure"
cat openspec/changes/bootstrap-laravel-app/tasks.md
```

**Step 2 — Approva** (il gate umano: il loop parte solo se esiste il file `APPROVED`):

```bash
touch openspec/changes/bootstrap-laravel-app/APPROVED
git add openspec/changes/bootstrap-laravel-app/APPROVED
git commit -m "approve: bootstrap-laravel-app"
```

**Step 3 — Lancia il loop, dal terminale.** Prima 2 iterazioni sole, per guardare da vicino la parte più rischiosa (l'install Laravel); poi il resto:

```bash
./ralph.sh --change bootstrap-laravel-app 2     # osserva le prime 2 iterazioni
./ralph.sh --change bootstrap-laravel-app 15    # poi lascialo andare (riprende da dove era)
```

Per run lunghi senza che il Mac si addormenti: `caffeinate -i ./ralph.sh --change <name> <N>`.

**Step 4 — Monitora** (secondo terminale, opzionale — il loop è fatto per non essere guardato):

```bash
while sleep 60; do clear; grep -c '^\- \[x\]' openspec/changes/bootstrap-laravel-app/tasks.md; tail -3 log.md; done
```

Un'iterazione dura tipicamente 5–15 minuti. Ogni iterazione = 1 task = 1 commit sul branch `ralph/bootstrap-laravel-app`.

**Step 5 — Chiusura** (quando il loop stampa `CHANGE_COMPLETE`, exit 0) → segui il rituale §2.7.

**Step 6 — Retrospettiva (10 min).** Leggi `openspec/changes/archive/*/progress.md`: com'è andata? Se il PROCESSO ha avuto attriti (prompt ambigui, task troppo grossi, comandi sbagliati) → annota in `lessons.md` e/o correggi questa guida.

Se il loop si ferma male → §5.

---

## 2. Il rituale per ogni change (lo ripeterai ~40–50 volte)

### 2.1 Nuova finestra Claude Code in `newco-erp`
Il hook ti inietta `hot.md` automaticamente: la sessione sa già dove siete rimasti.

### 2.2 Controlla i gate
Guarda la tabella "Open stack decisions" in `CLAUDE.md`: il lavoro che stai per preparare attraversa un gate senza ADR? → prima la sessione ADR (§3). `/spec-to-change` comunque si ferma da solo se becca un gate aperto.

### 2.3 Prepara il change
In Claude Code:

```
/spec-to-change Module 0 — product spine, prima fetta
```

Esempi di target validi: `"Foundations — skeleton dei moduli"`, `"Module K — KYC e sanctions screening"`, `"Module S — voucher lifecycle"`. La skill: legge Build Workplan + PRD via subagent, ti propone il taglio della fetta, ti intervista SOLO su ciò che la spec non risponde, scrive gli artefatti, valida `--strict`, ti presenta il riepilogo.

**Il tuo lavoro qui è il più importante di tutto il processo:** rispondi senza fretta, pretendi la provenienza (`_Source: spec/...`) su ogni requirement, taglia lo scope se la fetta supera ~20 task ("dividila, fammi solo la prima parte").

### 2.4 Review fredda
Leggi `proposal.md`, `design.md`, `tasks.md` del change. Checklist:
- [ ] Ogni requirement cita spec/ o un'ADR (niente invenzioni)
- [ ] Ogni task è descrivibile in 2–3 frasi (= 1 iterazione)
- [ ] Ogni task ha acceptance + test hints concreti
- [ ] Le cose escluse sono dichiarate (e assegnate a un change futuro)

### 2.5 Approva
Dillo alla skill ("approvo") — crea lei il marker — oppure a mano:

```bash
touch openspec/changes/<name>/APPROVED
git add -A && git commit -m "approve: <name>"
```

### 2.6 Chiudi la finestra Claude e lancia il loop dal terminale

```bash
caffeinate -i ./ralph.sh --change <name> <N>     # N ≈ (n° task × 1.5) + 2
```

### 2.7 Chiusura (a `CHANGE_COMPLETE`)

```bash
# 1. Review del branch
git log --oneline main..ralph/<name>
git diff main...ralph/<name> --stat              # e i file che ti interessano

# 2. Verifica locale su PostgreSQL 17 — il loop gira su SQLite, ma SQLite-verde è necessario,
#    non sufficiente (knowledge/testing/rules.md): conferma il motore di produzione PRIMA del merge.
docker run -d --name pg -e POSTGRES_DB=newco_test -e POSTGRES_USER=newco -e POSTGRES_PASSWORD=newco -p 55432:5432 postgres:17
DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco php artisan test
docker rm -f pg

# 3. Merge e push
git checkout main && git merge --no-ff ralph/<name> -m "merge: <name>"
git push
git branch -d ralph/<name>
```

**4. Verifica semantica** — nuova finestra Claude Code, incolla:

> Verifica semantica del change `<name>` (già mergiato su main). Per ogni Requirement nei delta spec del change valuta: (1) **Completeness** — ogni task fatto, ogni requirement implementato, ogni scenario coperto da un test; (2) **Correctness** — il codice rispetta l'intento della spec, edge case inclusi; (3) **Coherence** — le decisioni di design.md si riflettono nel codice. Classifica ogni problema CRITICAL / WARNING / SUGGESTION. Solo report, non correggere nulla.

- **CRITICAL** → aggiungi i fix come task non spuntati in `tasks.md` del change e rilancia `./ralph.sh --change <name>` (oppure risolvi interattivamente sul momento). Poi ripeti la verifica.
- Pulito (o solo WARNING/SUGGESTION accettati — i SUGGESTION buoni finiscono in `knowledge/` o in un change futuro) → avanti.

```bash
# 5. Archive: i delta si fondono in openspec/specs/ (la documentazione vivente)
openspec archive <name> --yes
git add -A && git commit -m "archive: <name>" && git push
```

### 2.8 Avanti col prossimo change
**Mai due loop in parallelo** (stesso repo, stessi file di stato). Sequenza, sempre.

---

## 3. Le sessioni ADR (i gate dello stack)

Quando un gate si avvicina, apri una finestra Claude Code e incolla:

> Sessione ADR con la disciplina grill-with-docs: dobbiamo decidere **<argomento>** (gate: <cosa sblocca>). Leggi `decisions/INDEX.md` e le ADR esistenti, le sezioni rilevanti di `spec/` e `CLAUDE.md`, poi proponi 2–3 opzioni argomentate per il NOSTRO contesto e grillami una domanda alla volta finché la decisione non è solida. Output: ADR in `decisions/YYYY-MM-DD-<topic>.md` (formato in `.claude/CLAUDE.md`) + riga in `decisions/INDEX.md` + spunta nella tabella gate di CLAUDE.md… anzi no: CLAUDE.md è protetto, dimmi tu la riga da aggiornare e la cambio io a mano.

(Nota: la tabella dei gate in `CLAUDE.md` la aggiorni tu a mano quando un'ADR chiude un gate — è un file protetto per gli agenti.)

**Ordine consigliato delle ADR:**

| # | ADR | Quando |
|---|---|---|
| 1 | DB engine di produzione (PostgreSQL vs MySQL) | Subito dopo lo smoke test |
| 2 | Event substrate + audit/event store (in-process + outbox?) | Prima di `foundations-domain-events` |
| 3 | Auth/identity (operatori vs clienti) | Prima di Module K |
| 4 | Queue driver | Prima del primo workflow async (S/E) |
| 5 | Object storage (fatture) | Prima di INV1 (Module S) |
| 6 | Frontend consumer/producer — direzione founder: TanStack SPA (TypeScript, no PHP lato frontend); l'ADR al gate formalizza API layer, auth customer, i18n ×6, SSR/SEO | Prima delle fette storefront di Module S |
| 7 | Hosting EU | Prima dello staging (F7) |

---

## 4. La roadmap: dal bootstrap al go-live

L'ordine è il **Build Workplan** (`spec/05-release/Build_Workplan_v0.3-MVP.md`). I change indicati sono indicativi: il taglio vero lo fate tu + `/spec-to-change`, fetta per fetta. Regola: moduli della stessa fase puoi alternarli (0-fetta-1, K-fetta-1, 0-fetta-2…), mai parallelizzarli.

| Fase | Cosa | Change indicativi | Gate/Note |
|---|---|---|---|
| **F0** | Smoke test | `bootstrap-laravel-app` | — ← sei qui |
| **F1** | Foundations | `foundations-modules-skeleton` ✅ (app/Modules, boundaries, provider) · `foundations-domain-events-audit` ✅ (event base + log immutabile) · `foundations-money-i18n-flags` ✅ (Money, locales, Pennant, actor_role) | ADR 1+2 ✅ · **F1 completata 3/3** |
| **F2** | Module 0 + K | 0: spine prodotti → formats/case → lifecycle+approval → SKU sellable → console · K: parties core → club+membership (Hero invariant) → KYC/sanctions → Hold → club credit → GDPR/retention → console | ADR 3 prima di K |
| **F3** | Module A + D | A: allocation+FSM+terms → sub-pool+L1 no-oversell → release primitive → publish+console · D: intent+PO → inbound 2-fase+discrepancy → consignment V2 → console | |
| **F4** | Module S (il più grosso) | offer FSM → cart+checkout gate (KYC/Hold) → order FSM → voucher 7-state+cellar → INV1+OC 5% → storage+INV2/INV3 → refund+14gg → storefront → console | ADR 4–6 lungo il percorso |
| **F5** | Module B **poi** C | B: InboundBatch+StockPosition → **L2 ATP push (critical path!)** → receiving+quarantine → stocktake+adjustment+interlock → serializzazione NFC → Logilize B-streams → console · C: shipping order FSM → eligibility+white-glove → pick FIFO+no-oversell-at-pick → Logilize C-streams → excise/INV2 → returns/recall manuali → console | B integration-ready PRIMA che S/C chiudano le integrazioni ATP |
| **F6** | Module E + integrazioni | eventi finanziari+dual-currency → Airwallex (pay/refund/chargeback) → Xero+documenti → settlement recording manuale + **emissione SupplierPaymentCompleted (R4)** → dunning INV3 manuale → finance-ops console · console cross-module (discrepancy queue B+C, white-glove) | **Credenziali sandbox Airwallex/Xero/HubSpot le procuri TU prima di approvare** |
| **F7** | QA & hardening | per modulo: `acceptance-hardening-<x>` (scenari di `spec/03-acceptance/` come test E2E) · change `improve-codebase-architecture` · `/security-review` · performance | ADR 7 |
| **F8** | Go-live prep | seeding, runbook operatori, checklist lancio, NFT unflag readiness (gate EXT-1 esterno) | |

**Admin Panel:** non è una fase a sé — le console di ogni modulo vivono nei change del modulo (Filament resources incluse nelle fette); solo le console cross-module sono change dedicati (F6–F7).

**Numeri realistici:** ~35–50 change, ~400–600 task totali, 1 task ≈ 1 iterazione ≈ 5–15 min macchina. La tua attenzione serve ai bordi (ADR, prepare, approve, review, verify): ~1–2 ore tue per change.

---

## 5. Quando il loop si ferma (playbook)

| Exit | Significato | Cosa fai |
|---|---|---|
| **0** | `CHANGE_COMPLETE` | Rituale di chiusura §2.7 |
| **1** | Iterazioni esaurite, task rimasti | Normale sui change grossi: guarda il progresso e rilancia `./ralph.sh --change <name>` (riprende dai checkbox) |
| **3** | `HUMAN_NEEDED` — l'agente chiede te | Leggi `progress.md` (in fondo) e `.last-output`. Tipico: gate ADR, contraddizione nella spec, credenziale mancante. Risolvi (ADR / correzione artefatti / .env), poi rilancia |
| **4** | Stallo: 3 iterazioni senza progresso | Leggi i `> ⚠ FAILED` in `tasks.md` e `progress.md`. Poi finestra Claude: *"Il loop è in stallo sul task X del change Y: leggi progress.md e .last-output, riproduci il problema con la disciplina diagnose e risolvilo con me sul branch ralph/Y."* A fix fatto (commit sul branch, checkbox spuntata a mano se il task ora è completo), rilancia il loop |
| **5** | Violazione di integrità: commit del loop hanno toccato `spec/`, `openspec/specs/`, file della macchina o un `APPROVED` | Trova i commit (`git log --oneline main..ralph/<name>`), `git revert` di quelli incriminati sul branch, indaga in `progress.md` perché l'agente ci ha provato → `lessons.md`, poi rilancia |

Casi rari: commit sporco sfuggito al quality loop → `git revert <sha>` sul branch + nota in `lessons.md`; artefatti del change da correggere in corsa → modificali, `openspec validate <name> --strict`, rilancia (i task già spuntati restano fatti).

---

## 6. Igiene ricorrente

**Fine giornata:** `/daily-log` (la tua skill globale) + `git push`.

**Fine settimana (~30 min):**
1. `tail -50 log.md` + `openspec list` — il quadro onesto della settimana
2. `knowledge/*/hypotheses.md` — qualche 3/3 da promuovere a regola?
3. `lessons.md` — le correzioni della settimana sono state registrate?
4. `hot.md` dice la verità? (se no, correggilo)
5. Ogni 2–3 settimane: un change di manutenzione con `improve-codebase-architecture`

**Ogni merge su main:** push (GitHub è il backup).

---

## 7. Regole d'oro

1. Mai modificare `spec/**` né `openspec/specs/**` a mano (la verità cambia solo via archive).
2. Mai approvare un change senza averlo letto. L'APPROVED è la TUA firma.
3. Un solo loop alla volta; mai dentro una finestra Claude Code.
4. Main sempre verde; il loop lavora solo su `ralph/<change>`.
5. Loop fermo? Prima leggi `progress.md`, poi decidi. Niente fix alla cieca.
6. Una sessione interattiva = uno scopo (ADR ≠ prepare ≠ verify ≠ debug).
7. Ogni tua correzione a Claude deve finire in `lessons.md` — pretendilo.
8. Niente "decidiamo dopo" sui gate ADR: bloccano il lavoro a valle per design.
9. Credenziali e account esterni li prepari tu PRIMA di approvare i change che li usano.
10. Questa guida è viva: processo cambiato = guida aggiornata.

---

## 8. Cheatsheet

```bash
# ── Preparazione ──────────────────────────────────────────────
/spec-to-change <target>                          # in Claude Code
touch openspec/changes/<name>/APPROVED            # approvazione manuale
git add -A && git commit -m "approve: <name>"

# ── Loop ──────────────────────────────────────────────────────
caffeinate -i ./ralph.sh --change <name> <N>      # N ≈ task×1.5+2
./ralph.sh --change <name>                        # riprende, default 10 iter

# ── Monitoraggio ──────────────────────────────────────────────
grep -c '^\- \[x\]' openspec/changes/<name>/tasks.md
cat openspec/changes/<name>/progress.md
tail -20 log.md && cat hot.md
git log --oneline -10
openspec list && openspec status --change <name>

# ── Chiusura ──────────────────────────────────────────────────
git log --oneline main..ralph/<name>
#   → verifica locale su PostgreSQL 17 PRIMA del merge (recipe docker completa in §2.7)
git checkout main && git merge --no-ff ralph/<name> -m "merge: <name>"
git push && git branch -d ralph/<name>
#   → verifica semantica in Claude Code (prompt in §2.7)
openspec archive <name> --yes
git add -A && git commit -m "archive: <name>" && git push

# ── Qualità (quando l'app esiste) ─────────────────────────────
vendor/bin/pint && php artisan test && vendor/bin/phpstan analyse
```
