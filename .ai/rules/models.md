---
paths:
  - 'app/Models/**'
---

# Models

## Mass assignment via #[Fillable] attribute
Declare mass-assignable attributes with the `#[Fillable([...])]` class attribute (`Illuminate\Database\Eloquent\Attributes\Fillable`), not a `protected $fillable`/`$guarded` property.
