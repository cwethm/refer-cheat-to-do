# Application Design

## Scope

This repository initializes the backend foundation for MVP 1 of an information continuity and ToDo system.

## Architectural direction

- API-first CakePHP 5 application
- PostgreSQL as the primary datastore
- thin controllers with business rules delegated to services/entities/tables
- backend-enforced authorization and validation
- JSON response and error conventions for API endpoints

## Deferred capabilities

The following future systems are intentionally not implemented in this initialization slice:

- research-agent orchestration
- document generation pipelines
- advanced AI-provider integrations
- full domain schema and workflow engine

## Initial API convention

Successful API responses use a consistent `data` envelope.

Error responses use:

```json
{
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "The submitted data is invalid.",
    "details": {}
  }
}
```
