---
paths:
  - 'app/Mcp/Tools/NethSecurity/**'
---

# Neth Security

## Extend NsApiTool for ubus-backed tools
A NethSecurity tool that is a single ubus call extends the abstract `NsApiTool` (in this same directory) instead of `Tool` directly. Set `$path`, `$method`, and `$maskedFields` (dotted paths, `*` for list items), and override `parameters(Request $request): array` for the call's arguments. `NsApiTool::handle()` already runs the ubus command over the shared `RemoteExecutor` and masks secrets via `SecretMasker` — don't duplicate that logic in the subclass.
