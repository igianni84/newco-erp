# Errata per Paolo — Separation of Duties: stessa persona può creare *e* approvare?

**Da:** Giovanni · **A:** Paolo (owner spec `c-mless/documentation`) · **Data:** 2026-06-17
**Ambito:** Creator → Reviewer → Approver lifecycle (Module 0 catalog + Module K producer + Admin Panel)
**Tipo:** richiesta di ratifica — la spec attuale dice il contrario di quanto emerso in call

---

## Il punto

Nella call del 2026-06-16 hai precisato che **chi crea può anche approvare**, a patto di **avere i ruoli** che lo autorizzano (separazione dei compiti basata sui *ruoli*, non sull'*identità delle persone*).

La spec attuale (`handoff/`, commit `4f48277`) dice però l'opposto, e in modo esplicito — è l'**unico** vincolo di approvazione tenuto *non* configurabile:

- **Admin Panel PRD §5.2:** *"the spec text itself requires two-or-more distinct actors, **independent of which roles they hold** … self-approval is never allowed."*
- **Module 0 PRD §4.1:** Reviewer e Approver *"cannot be the same person as the Creator on a given entity."*
- **Module 0 PRD §4.2 / §13.2 (BR-Lifecycle-1):** il **numero** di step è configurabile (3 → 2), ma *"the separation-of-duties floor … self-approval is never allowed … holds at any configured depth."*
- **Module K PRD §2 / §4.4 (Q3):** stessa regola per il contenuto Producer.
- **MVP-DEC-007:** conferma il floor multi-attore come unica disciplina PRD-level.

Quindi oggi: **role-count configurabile (3→2), ma attori sempre persone distinte**. La tua precisazione richiede invece che, con i ruoli giusti, **una sola persona** possa fare più step (incluso create + approve).

## Perché ti scrivo invece di cambiare e basta

Lato sviluppo **abbiamo già implementato e testato** il floor com'è scritto ora: il change `catalog-lifecycle-approval` *rifiuta* il self-approval e pretende attori distinti (sia a role-count 2 che 3); idem per `parties-producer-lifecycle`. Per allinearci alla tua precisazione serve prima la **ratifica in spec** (la possiedi tu) e poi un **change dedicato** con nuovi acceptance. Non tocchiamo il codice finché non confermi (è un controllo di tipo compliance: non lo rovesciamo su una nota verbale).

## Cosa mi serve da te (per aggiornare la spec)

1. **Confermi** la rilassatura: stessa persona può create **e** approve se ha i ruoli?
2. **Rimozione totale** del distinto-attore, oppure **toggle configurabile** (`require_distinct_actors`, default ON) rilassabile per il piccolo team di lancio? *(Consiglio il toggle: mantiene il seam e l'audit, in linea con "approval is admin-configurable".)*
3. Vale per **entrambi** Module 0 (catalog) **e** Module K (producer)? E per gli altri pattern multi-attore §5.2 (supervisor-override, single-supervisor-approval)?
4. L'**audit** resta invariato — ogni step registrato con attore + timestamp + decisione? *(do per scontato di sì)*
5. Vincoli di **compliance/audit** sulla SoD da preservare comunque?

## Dove andrebbe ratificato in `handoff/`

Module 0 PRD §4.1/§4.2/§13.2 · Module K PRD §2/§4.4 · Admin Panel PRD §1.4/§5.2 · MVP-DEC-007 (o una nuova DEC che lo supera). Appena è in spec, apriamo il change e ri-testiamo.

---

*Tracciato lato build in `decisions/2026-06-17-approval-separation-of-duties-role-gated.md`.*

---

## Versione Slack (pronta da inviare)

> Ciao Paolo 👋
>
> Sulla call di ieri: mi hai detto che nel flusso **Creator → Reviewer → Approver** la stessa persona può creare *e* approvare, purché abbia i ruoli per farlo.
>
> In documentazione però adesso è scritto l'opposto — è anzi l'unico vincolo tenuto *non* configurabile:
> • *Admin Panel §5.2* → "distinct actors, **independent of which roles they hold**; self-approval is never allowed"
> • *Module 0 §4.1/§4.2* e *Module K §4.4* → Reviewer/Approver "cannot be the same person as the Creator"; configurabile è solo il numero di step (3→2), non il "persone distinte".
>
> Lato dev l'abbiamo già implementato e testato così (self-approval bloccato), quindi per allinearlo mi servirebbe che lo **ratificassi in doc** e poi apriamo la modifica — non lo cambio di mia iniziativa perché è un controllo compliance.
>
> Due dubbi per aggiornarlo bene:
> 1️⃣ Rimozione totale del "persone distinte", o un **toggle configurabile** (default ON, rilassabile per il team ristretto di lancio)?
> 2️⃣ Vale per entrambi catalog (Mod 0) e producer (Mod K)? L'audit resta com'è (ogni step tracciato), giusto?
>
> Sezioni da toccare: Module 0 §4.1/§4.2/§13.2 · Module K §2/§4.4 · Admin Panel §1.4/§5.2 · MVP-DEC-007. Grazie! 🙏
>
> (P.S. il fix sul KYC producer — `not_required` che sblocca come `verified` — l'ho visto, tutto chiaro, lo implementiamo così 👍)
