# ADR 0003 — Shared resources do not widen activity visibility

## Status
Accepted (Slice 18)

## Context
Activity records may describe changes to a Project, Notebook or other resource that is now visible
to more than one user. Exposing another actor's history could reveal actions and metadata beyond the
resource data granted by a capability. Activity records are intentionally actor-scoped today.

## Decision
Keep activity history visible only to the actor who produced each entry. A capability grant, resource
ownership, Library membership, or participation in a cross-context request does not widen activity
visibility.

This is enforced at the existing service boundary: `ActivityRecorder::forActor()` filters by the
authenticated actor, and `ActivityController` only requests that actor's records. No shared-resource
activity query or client surface is added.

## Consequences
- Sharing a resource does not reveal its activity stream or the identity and actions of other users.
- Users may see the outcome of a collaboration through the resource and request APIs, but not another
  person's audit history.
- Any future proposal to widen activity visibility must define the visible entry types, metadata
  redaction, service authorization, database query scope and denied-path tests before implementation.
