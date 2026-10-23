# Pointer

Pointer is a Laravel application that serves an MCP server, so an AI agent can inspect NethServer and NethSecurity
machines. It reaches a target only through a support server, which owns the trust relationship with every target and
brokers the second hop. Answers are grounded with the product documentation retrieved from kapa.ai and with playbooks,
which agents record and admins review in the web panel.

## Prerequisites

Docker with Compose v2. PHP, Node and Postgres all run in containers, nothing else is needed on the host.

The application also expects these external services. Without them it boots, but the tools that reach a target or the
documentation fail.

- **A support server** reachable over SSH, running the `sancho` session helper, plus a private key that authenticates
  Pointer against it.
- **kapa.ai credentials**: an API key and a project ID, from *Project > API Keys* in the kapa.ai dashboard.

## Setting up a dev machine

```bash
git clone git@github.com:Tbaile/pointer.git
cd pointer
cp .env.example .env
```

Edit `.env` and fill in:

```dotenv
# Support server that brokers access to target machines.
POINTER_SUPPORT_HOST=
POINTER_SUPPORT_USER=pointer
POINTER_SUPPORT_PORT=
# Private key, as a multi-line value in double quotes.
POINTER_SUPPORT_IDENTITY=

# kapa.ai retrieval API.
KAPA_API_KEY=
KAPA_PROJECT_ID=
```

Leave `POINTER_SUPPORT_PORT` empty to use the default SSH port. The key is written into the container on every start, so
there is no key file to place anywhere.

Then start everything:

```bash
docker compose up
```

The first start builds the image, so it takes a while. The `app` container then installs the dependencies, generates the
application key and the OAuth keys, runs the migrations and creates the administrator from `ADMIN_EMAIL` and
`ADMIN_PASSWORD` in `.env` (`admin@example.com` / `password` by default). Next starts skip what is already there. Once
`app` is healthy it is ready.

| Service | What it does | Where |
| --- | --- | --- |
| `app` | The web panel and the MCP server | <http://localhost:8000> |
| `node` | Vite dev server with hot reload | <http://localhost:5173> |
| `postgres` | The database, kept in the `postgres-data` volume | not published |

## Running it

Log in at <http://localhost:8000> with the administrator account. The panel at `/admin` has the playbooks, the users, the
MCP activity log and an MCP setup page with the steps to connect an agent. The MCP server itself is served at
`/mcp/pointer`, behind OAuth.

Two commands help verify the external pieces before pointing a model at a machine:

```bash
# Check that the support server hop works.
docker compose exec app php artisan pointer:run <uuid> --command="uname -a"

# Check that documentation retrieval works.
docker compose exec app php artisan kapa:query:nsec "how do I configure a wan failover"
```

`docker compose down` stops everything and keeps the database. Beware, `docker compose down -v` deletes the
`postgres-data` volume too, and with it every playbook.

## Checks

```bash
docker compose exec app composer test            # config clear + Pint + PHPStan + Pest
docker compose exec app composer lint            # fix code style
docker compose exec app composer types:check     # PHPStan only
docker compose exec node npm run format:check    # Prettier on Blade views
docker compose exec node npm run build           # production asset build
```

The tests run on their own `pointer_testing` database, so the development data is safe.

CI runs Pint, PHPStan, Prettier and Pest on every push and pull request against `main`.

## License

AGPL-3.0-or-later.
