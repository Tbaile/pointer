# Pointer

Pointer is a Laravel application that lets an AI agent inspect NethServer and NethSecurity machines. It reaches a target
only through a support server, which owns the trust relationship with every target and brokers the second hop, and it
grounds its answers with the product documentation retrieved from kapa.ai.

## Prerequisites

| Requirement | Version | Notes |
| --- | --- | --- |
| PHP | 8.5 | `composer.json` pins the platform to 8.5. |
| Composer | 2.x | |
| Node.js | v24.20.0 | Pinned in `.nvmrc`; `nvm use` picks it up. |
| npm | 11.x | Ships with Node 24. |
| SQLite | — | Default database driver; needs the `pdo_sqlite` PHP extension. |
| `ssh` client | — | Used to reach the support server. |

The application also expects these external services. Without them it boots, but the agent cannot run.

- **An OpenAI API key.** `PointerAgent` is pinned to the OpenAI provider and the `gpt-5.6-luna` model.
- **A support server** reachable over SSH, running the `sancho` session helper, plus a private key that authenticates
  Pointer against it.
- **kapa.ai credentials** — an API key and a project ID, from *Project > API Keys* in the kapa.ai dashboard.

## Setting up a dev machine

```bash
git clone git@github.com:Tbaile/pointer.git
cd pointer
nvm use
composer setup
```

`composer setup` installs the PHP and JS dependencies, copies `.env.example` to `.env`, generates the application key,
runs the migrations and builds the frontend assets.

### Configure the environment

Edit `.env` and fill in:

```dotenv
# Provider credentials for the agent, which runs on OpenAI.
OPENAI_API_KEY=

# Support server that brokers access to target machines.
POINTER_SUPPORT_HOST=
POINTER_SUPPORT_USER=pointer
POINTER_SUPPORT_IDENTITY_FILE=app/private/pointer

# kapa.ai retrieval API.
KAPA_API_KEY=
KAPA_PROJECT_ID=
```

`POINTER_SUPPORT_IDENTITY_FILE` is resolved against the storage path when relative, so the default points at
`storage/app/private/pointer`. Put the private key there and give it `0600`.

### Seed the admin account

The web UI is behind authentication. `DatabaseSeeder` creates a single administrator from `ADMIN_EMAIL` and
`ADMIN_PASSWORD` in `.env` (`admin@example.com` / `password` by default):

```bash
php artisan db:seed
```

## Running it

```bash
composer dev
```

That runs `php artisan dev`, which starts four processes together: the PHP dev server, the queue listener, the Pail log
tailer and the Vite dev server. The application is served at <http://localhost:8000>.

To drive the agent from the terminal instead of the browser:

```bash
# Start a session against a target, by its sancho session id.
php artisan pointer:agent <sos-id>

# Continue the previous conversation for that system.
php artisan pointer:agent <sos-id> --continue
```

Two commands help verify the external pieces before pointing a model at a machine:

```bash
# Check that the support server hop works.
php artisan pointer:run <uuid> --command="uname -a"

# Check that documentation retrieval works.
php artisan kapa:query:nsec "how do I configure a wan failover"
```

## Checks

```bash
composer test          # config clear + Pint + PHPStan + Pest
composer lint          # fix code style
composer types:check   # PHPStan only
npm run format:check   # Prettier on Blade views
npm run build          # production asset build
```

CI runs Pint, PHPStan, Prettier and Pest on every push and pull request against `main`.

## License

AGPL-3.0-or-later.
