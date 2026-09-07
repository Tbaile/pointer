# Verification After Editing

Never finish a task on "the edit looks right". After changing files, run the checks that cover what you touched, and fix what they report. Discover the exact commands from `composer.json` scripts, `package.json` scripts, and `.github/workflows/` — never invent a command.

## Always Run

| You changed | Run |
| --- | --- |
| Any `.php` file | `vendor/bin/pint --dirty --format agent` then `composer types:check` |
| Any `.blade.php` file | `npm run format` (or `npm run format:check` to only inspect) |
| Frontend assets (`resources/css/**`, `resources/js/**`, `vite.config.js`) | `npm run build` |
| Any application or test code | The narrowest passing test run: `php artisan test --compact --filter=testName` or a file path |

- Static analysis (`composer types:check`, PHPStan) is not optional. A green test run with new PHPStan errors is a failing change.
- Fix failures you caused. Do not silence them with baselines, `@phpstan-ignore`, `--no-verify`, skipped tests, or loosened assertions unless the user approves.
- If a check fails for a reason unrelated to your change, say so explicitly rather than ignoring it.

## Before Reporting Done

Run the full gate the CI runs, or ask the user to: `composer test` (config clear + Pint check + PHPStan + Pest). CI also runs `npm run format:check` and `npm run build`, so both must pass too.

Report results honestly: name the commands you ran, quote the decisive failing line when something breaks, and state plainly which checks you skipped and why.
