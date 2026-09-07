---
paths:
  - 'tests/**'
---

# Tests

## Use Pest\Laravel function imports for HTTP and setup helpers
Import the helpers you need (`use function Pest\Laravel\get;`, `post`, `actingAs`, ...) and call them bare — `get('/login')->assertOk()`, `actingAs($user)->get('/')`. Do not start a chain with `$this->get(...)` or `$this->actingAs(...)`.

Auth-state assertions stay on the test case: `$this->assertGuest()`, `$this->assertAuthenticatedAs($user)`.
