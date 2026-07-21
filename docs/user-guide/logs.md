# Vote logs

Open **Democracy Poll → Logs** to inspect recorded votes. Logs support repeat-vote checks and revoting, so deleting them can change more than the visible history.

![The vote-log screen with fictional voter data.](/screenshots/admin-logs.png){.doc-screenshot}

The log table can display the poll, answer, voter, date, IP address, and IP geolocation details. IP details are fetched on demand from the external `ipwho.is` service and may be unavailable or rate-limited.

## Delete logs carefully

- **Delete only logs** preserves the displayed vote totals but removes the server-side records used to validate them.
- **Delete logs and votes** also subtracts the related totals.
- Bulk cleanup can remove logs belonging to closed polls or all logs.

Back up the database before bulk deletion. Treat IP addresses and account-linked voting records as personal data under the privacy rules that apply to your site.
