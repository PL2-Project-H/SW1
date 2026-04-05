# SpecialistHub

Run the project with:

```bash
docker compose up --build
```

Open `http://localhost:8080/front/index.html`.

Operational notes:

- Evidence bundles are written to `storage/evidence/` at the project root. `/storage` must not be served by Apache or any other web server.
- Milestone auto-approval is no longer triggered during controller construction. Run it explicitly from cron or another scheduler with:

```bash
* * * * * curl -s -X POST http://localhost/api/admin.php?action=milestones/auto-approve
```

- Payout processing is now an explicit background job for `financial_admin` automation:

```bash
* * * * * curl -s -X POST http://localhost/api/admin.php?action=escrow/process-payouts
```
