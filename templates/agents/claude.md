# PachyBase Claude Template

Claude clients can use the same project token surface as Codex.

Suggested bootstrap steps:

1. Provision the project.
2. Save the bootstrap token in the client environment.
3. Pass the tenant header on every request.
4. Route long-running outbound actions through async jobs and webhooks.
