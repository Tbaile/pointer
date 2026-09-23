---
paths:
  - 'database/migrations/**'
---

# Migrations

## Enum-backed columns are string(), not enum()
Store PHP-enum-backed columns as `$table->string('column')`, never the native `$table->enum(...)` migration type. The PHP enum cast lives on the model's `casts()` method.
