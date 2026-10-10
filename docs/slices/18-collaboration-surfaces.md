# Slice 18 — Collaboration surfaces: capabilities and cross-context requests

## Slice Contract

### Status
PROPOSED

### Goal
Bring the collaboration model delivered in Slices 10 and 11 into the client: granting and revoking
capabilities on a resource, and sending, reviewing and responding to cross-context requests.

### In scope
- A capability view per resource showing active grants, with grant and revoke actions.
- A cross-context request inbox and outbox with accept, decline and callback actions.
- Presenting the default-deny outcome honestly: when the API denies an action, the client explains
  the denial rather than hiding the failure.
- An ADR deciding whether activity history remains actor-only once resources are genuinely shared.

### Out of scope
- Any change to the capability vocabulary, grant semantics, or the cross-context request state
  machine.
- Notifications, email, or any out-of-band delivery channel.
- Agent permissions.

### Dependencies
Slice 10 (capability model), Slice 11 (cross-context requests and callbacks), Slice 17 (activity
history view, which the ADR may change).

### Design constraints
- Authorization is evaluated once, on the server, by `CapabilityService`. The client must never
  re-implement the rules; it may only reflect what the API returns.
- Self-granting is impossible in the database; the client must not present it as an option.
- Cross-context requests store typed source/target identifiers without resource foreign keys, so the
  client must tolerate a referenced resource that no longer exists.

### Required decision
Activity history is currently visible only to the actor who produced it. Once a resource can be
shared, that rule has to be re-examined deliberately. The slice must produce an ADR that either
keeps actor-only visibility and states why, or defines exactly who may see which entries and how
that is enforced in the service layer and in the database.

### Test plan
- Browser tests for: granting a capability; revoking it; a denied action producing a readable
  explanation; sending a cross-context request; accepting and declining one; and a request whose
  target resource has been deleted.
- PHPUnit coverage for any authorization change the ADR requires, including an explicit denied-path
  test for every newly visible entry type.
- Accessibility audit and responsive overflow coverage for the new views.
- Standing client security checklist unchanged and passing.

### Risks
- This is the first client surface where one user's action affects another user's data. Every new
  read path needs an explicit denied-path test, not only a happy-path test.
- An activity-visibility change is a privacy change. It must not be made implicitly as a side effect
  of building a view.
