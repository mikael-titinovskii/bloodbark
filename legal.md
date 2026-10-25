# Legal scope

Everything the project is legally and editorially bound by: what it is, what it must never become, what has to exist
before launch, the closed set of fields and labels, the strings that ship, and what must be escalated instead of
decided.

`AGENTS.md` covers the stack only. This file is the reference for anything a user reads or a regulator could ask about.
If a request would cross one of the settled decisions below, stop and say so rather than building it.

---

## Role

**Role:** information and matching platform connecting dog owners. Not a veterinary service. Not an emergency service.
Not a broker of biological material.

**Legal anchors:** personal data — Law 195/2024 (GDPR-model, in force 23.08.2026); veterinary profession reserved — Law
121/2020; animal by-products regime attaches to acts on the material — Law 129/2019; e-commerce needs registration — Law
284/2004 art.9 (3);
abusive clauses void — Law 105/2003 art.5.

---

## Decisions already made — do not revisit

Settled with the owner. Do not "improve" on these, do not propose alternatives, do not implement a feature that requires
reversing them.

1. **Zero revenue, forever.** No ads, no fees, no paid promotion, no sponsorship, no donations of any kind, no payment
   processing integrated for any purpose. This keeps the project outside Law 284/2004 registration and outside
   consumer-contract exposure entirely.
2. **No custody of biological material.** Never store, transport, arrange transport for, handle, or hold blood or any
   sample. Connect owners only. The by-products regime attaches to acts on the material, not to introductions.
3. **Filter, never decide.** The user supplies the criterion. The system displays and filters declared facts. It never
   concludes applicability to any specific animal.
4. **Dogs only.** Cats deferred — feline alloantibodies make a first mismatch rapidly fatal.

If a request would cross one of these, stop and say so rather than building it.

---

## Never build

- Compatibility output: no "match", "compatible", "suitable", "safe", score, fit %, or ranking by fit. No exceptions, no
  soft phrasing that means the same thing.
- Clinical advice, dosage, procedure steps, or any instruction to transfuse.
- Any logic — AI or rules — concluding fitness for a specific recipient. AI extracts and displays fields only.
- Upload of medical documents, scans, invoices, lab PDFs. Blood type is one enumerated field.
- Public owner identity: no name, phone, email, address, or social handle in search output or any public page.
- Exact addresses. District or city only.
- Custody, storage, transport, or logistics features of any kind.
- Waiver or liability-exclusion clauses. No "entertainment purposes" or equivalent framing.
- Any revenue, donation, or payment feature.
- Scraped clinic or owner data.
- Donor ratings, donation leaderboards, or public comment threads about clinics or vets.
- Any field outside the schema below.
- General monitoring or proactive triage of user content — you are not the emergency pathway.

## Required before launch

- Explicit consent checkbox, logged with timestamp and policy version.
- Privacy policy and ToS in RO and RU.
- One-page processing register (ROPA).
- Working data export and deletion, self-serve or admin.
- Written breach plan: detect → assess → notify CNPDCP → inform affected users.
- Processor agreement with hosting and any AI provider, including no-training clause.
- Hosting in EU or MD; if elsewhere, transfer safeguards documented.
- Listing expiry with re-confirmation ping.
- Report button plus moderation action log.
- Age 18+ gate.
- Scope banner site-wide; emergency notice on search and on all empty states.

---

## Listing schema (closed set)

```

species · breed · weight_kg · age_years
blood_type        DEA 1.1 | 1.2 | 3 | 4 | 5 | 6 | 7 | Dal | unknown
blood_type_source vet_confirmed{clinic, date} | owner_reported
district · available_from · available_to · verification_state · expires_at

```

Owner contact lives in a separate record. Never joined into search output. Contact is relay-only, or vet-initiated where
the donor has opted in.

---

## Copy rules

Every label describes a property of the donor. Never a relation to the searcher's dog.

- OK: `DEA 1.1 negative donors · Chișinău · clinic-verified`
- BAD: `Matching donors for your dog`
- OK: `3 new listings match your saved filter`
- BAD: `We found donors we think will work for Rex`

Verification state visible on every listing. Unverified data must never look verified. Saved-search alerts are permitted
only as literal filter results, never as recommendations.

## Required strings

**Scope** (site-wide):
> We are an information and matching service. We do not provide veterinary advice, diagnosis, compatibility
> determinations, or transfusion services. All compatibility decisions and transfusions must be made by a licensed
> veterinarian.

**Emergency** (search + empty states):
> Not an emergency service. In an acute case, contact your veterinarian now.

**Empty state:** widen to neighbouring districts · save this search · contact your vet.

**Donor attestation:**
> I confirm a licensed veterinarian has assessed this animal as fit to donate and has determined its blood type.

---

## Escalate to a human, do not decide

- Any feature that would put the platform in custody, transport, or scheduling of the procedure.
- Any change to the revenue decision, including "temporary" or "test" monetisation.
- Any proposal to let the system rank, recommend, or score donors.
- Any request to add cats, add species, or add organ matching.
