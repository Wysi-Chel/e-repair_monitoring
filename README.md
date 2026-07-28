# Equipment Repair Monitoring

Internal IT repair tracking module for the MICEI Monitoring Systems portal.

## Open the system

1. Start Apache and MySQL in XAMPP.
2. Sign in at `http://localhost/micei_mis/`.
3. Open **Equipment Repair Monitoring** from the system launcher.

The application creates the `equipment_repair_monitoring` MySQL database and its tables automatically on first use. The same schema is also available in `database/schema.sql` for manual installation.

## Main functions

- Equipment registry with ownership, location, operating status, and repair history
- Repair requests with automatic `ER-YYYY-####` ticket numbers
- Priority, technician, diagnosis, repair action, parts, cost, and release tracking
- Repair workflow from Submitted through Completed or Cancelled
- Dashboard summaries, filters, CSV export, printable request details, and audit trail
- Shared login session with the MICEI portal
