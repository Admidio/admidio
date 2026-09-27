---
apply: always
---

# Admidio Development Rules

- Use PHP 8.2 compatible code.
- Follow the existing Admidio coding style and conventions.
- Prefer existing Admidio classes, functions, and helper methods instead of introducing duplicate functionality.
- Do not introduce new dependencies without a good reason.
- All user-facing strings must use the Admidio translation system.

## Database and Demo Data

- The demo data always represents the database state of the latest released Admidio version, not the current `master` branch.
- Do not modify the demo data when changing the database structure on `master`.
- All database structure changes required for the current development version must be implemented in the corresponding database update scripts.
- When implementing a database schema change, ensure that existing installations can be migrated from the latest released version to the current development version through the update process.
