# Slice 24 — AI agent integration

## Slice Contract

### Status
PROPOSED — third slice of the post-MVP domain program

### Goal
Allow an explicitly configured agent to act on a user's data within the existing default-deny
capability model, with every action attributable and reversible.

### In scope
- An agent identity that is distinct from a user account and cannot hold a password.
- Agent capability grants drawn from the existing closed vocabulary, always narrower than the
  granting user's own capabilities, with expiry and immediate revocation.
- A recorded, user-visible trail of every agent action, including the prompt or instruction that
  produced it.
- A human approval step for any mutating or destructive action, configurable per capability.

### Out of scope
- Autonomous scheduling or triggering (Slice 25).
- Agents acting across users, or an agent granting capabilities to another agent.
- Model selection, hosting, or prompt engineering decisions, which belong in an ADR rather than in
  the slice.

### Dependencies
Slices 10 (capabilities), 12 (activity history), 22, 23.

### Required threat and authorization review
Before implementation the slice must state: how agent credentials are stored and rotated; how an
agent is prevented from escalating beyond its grantor's capabilities; what prompt-injected content
can and cannot cause, given that ResearchCollection material is attacker-controllable in the general
case; what data leaves the system and to where; and how a runaway agent is stopped.

### Test plan
- PHPUnit: an agent can never exceed its grantor's capabilities; revocation takes effect
  immediately; expiry is enforced; destructive actions are blocked without approval; every action is
  recorded and attributable; denied paths for every endpoint.
- A test asserting that untrusted stored content cannot cause an agent to perform an action outside
  its granted capabilities.
- Migration apply/rollback/reapply.

### Risks
- Prompt injection through stored user content is the central hazard; capability enforcement must be
  server-side and independent of anything the model emits.
- "Agent convenience" is the most likely source of a silent authorization weakening in this project.
