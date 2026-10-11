# Slice 36 — Sharing cheat sheets with named people

## Slice Contract

### Status
PROPOSED — Phase F (`docs/intended-use.md`). The owner has confirmed that cheat sheets should be
shareable with named people. This is the first slice in which one user's content is shown to another,
so it **requires Slice 19 (accounts) and an ADR** before any implementation.

### Goal
Let the owner give named people, who have accounts on the same installation, read-only access to a
cheat sheet and the cards on it, so the community can use the reference material without the owner
re-typing or printing it, while records, parties, needs, decisions, links and everything else stay
private.

### In scope
The shape below is the leaning of the ADR (see Required decision), not a settled design.

- **Publishing.** The owner publishes a cheat sheet, which creates a read-only *publication*: a
  plain-text copy of the sheet's title and, in order, each card's title, `card_type`, summary, body
  and dialogue-pattern fields as they are at that moment, with a version number and `published_at`.
  Publishing again replaces the content in place and increments the version, so existing shares stay
  valid. Unpublishing removes the publication and every share of it.
- **Preview and exclusion.** Before publishing, the owner sees exactly what recipients will see and
  can leave out individual cards. Nothing else about a card is published: no links or backlinks, tags,
  issue or bundle membership, activity, or the owner's contact details.
- **Sharing.** The owner shares a publication with a named user, chosen by the exact account
  identifier. A share is a flat capability grant (ADR 0002): `read`, on exactly that publication, for
  exactly that user. The owner sees who a publication is shared with and can revoke any share.
  Self-sharing is rejected, in the application and by the database check from Slice 10.
- **Recipient view.** A **Shared with me** list and a read-only detail view showing the sheet, its
  cards, the version and date, and the owner's display name. Recipients cannot edit, copy into their
  own account, comment, suggest or re-share.
- **Honest limits in the client.** Revoking or unpublishing stops future reads; it cannot recall what a
  recipient has already read or copied. The client states this when sharing and when revoking.
- Account export coverage for the owner's publications and their share list, and activity entries for
  publish, republish, unpublish, share and revoke (actor-only, ADR 0003). Reads by a recipient are not
  recorded and there are no read receipts.

### Out of scope
- Sharing anything other than a cheat sheet publication. Records, parties, needs, decisions, issues,
  bundles, ToDos and the owner's cards themselves stay private; a bundle can never contain another
  user's publication.
- Public, anonymous or link-token access of any kind, and sharing with someone who has no account.
  Creating the account is Slice 19; sharing happens afterwards.
- Edits, contributions or suggestions from recipients (the capability vocabulary has `suggest` and
  `contribute`, and this slice grants neither), groups of recipients, comments, notifications and
  email.
- Live sharing of the owner's working copy, and per-field exclusion of card content, unless the ADR
  chooses them.
- Downloading or printing a shared sheet (Slice 23), and offline copies of shared sheets (Slice 35
  covers the owner's own bundles only).
- Linking a recipient to a party record (Slice 33), or any change to who may read what elsewhere.

### Dependencies
Slice 19 (accounts, invitation, password and session security: a named recipient must be able to have
an account, and its rate limiting protects the share lookup), Slice 26 (cheat sheets), Slices 10 and 11
(the capability model and `CapabilityService`, ADR 0002), 12 (activity), 21 (export), 17 (confirmation
pattern). Slice 23 is a later consumer, not a dependency.

### Required decision (ADR)
How a share reaches the cards on a sheet without breaking ADR 0002, which is flat and infers nothing.

1. **Published snapshot (the leaning).** The grant is on one publication and the publication already
   contains the cards, so no authority is inferred from a sheet to its cards. The owner reviews exactly
   what is shared; an edit to a card, or a card added later, reaches nobody until the owner
   republishes; and a mistaken sensitive line in a working card is not exposed by accident. The costs
   are republishing to update and a stored copy of the text.
2. **Live sheet grant.** The recipient sees the current sheet and its current cards. This needs a
   deliberate, narrow exception to ADR 0002 ("a `read` grant on a sheet authorises reading its member
   cards through the sheet endpoint only, never the cards independently"). It stays current without
   republishing and exposes every edit immediately, including mistakes.
3. **Per-card grants.** One grant per card plus one for the sheet, honouring ADR 0002 literally. It
   multiplies grants, drifts as the sheet changes, and does not say what the recipient will see.

The ADR must also decide the resource type name added to the closed set (`capability_grants_resource_type_chk`),
whether per-field exclusion is needed, and whether an unknown identifier and a known but unavailable
one return an identical response (the leaning is yes, to avoid account enumeration).

### Required threat and authorization review
- Publishing, sharing and revoking are owner-only. Reading is a `read` decision by
  `CapabilityService`, the single evaluation path, default deny. A non-grantee, a revoked grantee and a
  nonexistent publication all return `404`. No other capability is allowed by the grant.
- **First cross-user rendering.** Every field of a publication is the owner's untrusted text shown to a
  different user. It is returned as text and inserted with `textContent`, with hostile-content tests,
  and it is the reason the no-HTML-sink rule matters more here than anywhere before.
- **What is exposed is what the owner publishes.** Card text can mention third parties, and dialogue
  pattern fields (`avoid`, `rationale`) are the likeliest place for it. The preview, per-card
  exclusion and a plain warning are the controls. Parties, records, needs, decisions, links and
  backlinks are never included, and a test must show a publication contains none of them.
- **Account enumeration and abuse.** The share lookup uses an exact identifier, returns identical
  responses for unknown and unavailable accounts, and is rate limited (Slice 19).
- **Revocation and deletion.** Revoking takes effect on the next read. Deleting or unpublishing a sheet
  removes the publication and its grants in the same operation (cascade or `BEFORE DELETE` trigger), so
  no orphan grant can attach to a later row; the owner confirms that this removes shared copies.
- **Blast radius of a recipient account.** A compromised recipient session can read only what is
  shared with that user. A compromised owner session can publish and share, which is why the share and
  revoke actions are recorded in the owner's activity.
- The recipient's own export does not include content shared with them, because it is not theirs. The
  owner's identity shown to recipients is the display name only.
- Names, emails, identifiers and content are never logged.

### Test plan
- Migration apply, rollback and reapply, including the new grant resource type and the cascade or
  trigger on sheet deletion.
- PHPUnit:
  - the grantee reads and nothing else; a non-grantee, a revoked grantee, another owner and a
    nonexistent publication all get `404`; every other capability fails;
  - self-share rejected; sharing a publication the requester does not own rejected;
  - republish shows the grantee the new version and leaves the share valid; unpublish and sheet
    deletion remove the shares; excluded cards are absent;
  - a publication contains no link, backlink, tag, hub, bundle, party, record, need or decision data;
  - unknown and unavailable identifiers produce identical responses, and the rate limit applies;
  - hostile content in every published field is returned as inert text;
  - activity entries for the owner; no recipient read events; export contains the owner's publications
    and shares and none of another user's.
- Browser tests with two accounts (a new second-user fixture alongside the existing E2E accounts):
  the owner previews, excludes a card, publishes and shares; the recipient sees it under Shared with
  me and cannot edit; the owner republishes and the recipient sees the new version; the owner revokes
  and the recipient gets a not-found view. Accessibility and overflow checks;
  `tests/js/client-security.test.mjs` passing.

### Risks
- It is the first time one person's text is shown to another. Stored cross-user script injection and
  mis-sharing are the two failures that matter, and the controls above are aimed at them.
- A copy cannot be recalled. The client's honesty about that is the mitigation, not a guarantee.
- Sharing invites a request for comments, editing and groups. Each is a different authority question and
  needs its own contract; none should be added here to satisfy a single use.
