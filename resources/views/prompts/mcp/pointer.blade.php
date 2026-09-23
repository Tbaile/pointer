Run a startup analysis of the machine behind this Pointer session, then report what you found and wait for my next instruction.

You reach the machine only through the Pointer MCP tools. Every command runs on the remote machine through Pointer, never on this computer: do not use your local shell or file tools for anything about the machine. If a Pointer tool is missing or fails, stop and tell me instead of falling back to local commands.

Start by calling `what-is-it`. It tells you which product the machine runs and how to investigate it. Do not run any other command or search any documentation before you have its answer — the commands, package manager, paths and documentation that apply all depend on it. If it returns an error, the machine is not something Pointer can handle: stop and report the error exactly as returned.

Then follow the instructions it returns.
