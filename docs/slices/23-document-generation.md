# Slice 23 — Document generation

## Slice Contract

### Status
PROPOSED — second slice of the post-MVP domain program; Phase G, scheduled after Phase F

### Goal
Produce documents deterministically from data the user already owns — ToDos, Projects, Notebooks,
ResearchCollections and reference cards and cheat sheets (Slice 26) — with no model inference
involved. The first intended use is a printable cheat sheet, the fallback when there is no signal.

### In scope
- A document definition: template, bound data sources, and output format.
- Deterministic rendering with a recorded input snapshot, so the same inputs always produce the same
  output.
- Storage of generated documents with ownership, capability checks and activity history entries.
- Bounded generation: explicit limits on input size, output size and execution time.

### Out of scope
- Any AI or model-generated content (Slice 24).
- External orchestration or scheduling (Slice 25).
- Arbitrary user-authored template code.

### Dependencies
Slice 22 (ResearchCollection), Slice 26 (cards and cheat sheets), Slices 10 and 12. Discussion and
event records, and community needs (Slices 27 and 31), are not data sources unless a later decision
adds them with the sensitive-information constraints in `docs/intended-use.md`. A bundle (Slice 34) is
the natural data source for packaging a set of planning, meeting, discussion or topic material for
evaluation, but whether packaging is a rendered document at all depends on the owner's answer to
the "evaluation" question there; the bundle's sensitive-member exclusion would carry over. Printing or downloading a published cheat sheet that has been shared with a recipient (Slice 36) is a possible later consumer.

### Required threat and authorization review
Templates that interpolate user-supplied content are an injection surface. Before implementation the
slice must state: which template engine is used and whether it escapes by default; how untrusted
ResearchCollection content is escaped per output format; whether templates may be user-authored
(the default answer is no); and how generation is prevented from reading data the requester may not
read.

### Test plan
- PHPUnit: determinism — identical inputs produce identical output; escaping of hostile content for
  every supported output format; denied paths when a bound data source is not readable by the
  requester; size and time limits enforced; migration apply/rollback/reapply.
- A regression test asserting that no template path can execute arbitrary code.

### Risks
- Template injection and server-side template execution are the primary hazards. If a template
  engine capable of executing code is chosen, this slice becomes a remote-code-execution surface.
- Document generation can quietly become a second, weaker read path across the whole domain.
