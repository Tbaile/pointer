---
paths:
  - 'app/Contracts/**'
---

# Contracts

## Interface per capability, bound in a ServiceProvider
External/side-effecting capabilities (remote execution, knowledge retrieval, etc.) get an interface in `app/Contracts`, implemented by a single `final readonly class` in `app/Services/<Area>` with promoted constructor properties. Bind the interface to its implementation in a ServiceProvider's `register()` via `$this->app->bind(Contract::class, fn (): Impl => new Impl(...))`, reading config there — not inside the service class. Use contextual binding (`$this->app->when(Consumer::class)->needs(Contract::class)->give(...)`) when different consumers need different configuration of the same contract.

Consumers take the contract via constructor injection; never resolve it with `app()`/`resolve()`.
