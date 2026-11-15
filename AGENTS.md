# Admidio Development Rules

These rules apply repository-wide unless a more specific `AGENTS.md` applies. Direct maintainer
instructions take precedence.

- Use PHP 8.2 compatible code and the existing Admidio style. Reuse branch-local classes, helpers,
  and architecture; do not introduce dependencies or framework patterns without a concrete need.
- Inspect the closest implementation and affected callers before changing behavior or contracts.
  The checked-out branch is authoritative; verify APIs instead of inventing them.
- Keep changes focused on the task. Preserve unrelated working-tree changes, public compatibility,
  and existing line endings. Do not reformat unrelated files or edit generated/vendor assets.
- Validate inputs, authorize actions and record/organization access server-side, use existing CSRF
  and escaping mechanisms, and bind SQL data values. Never expose secrets or commit local data.
- All user-facing text uses the Admidio translation system, including accessible labels.
- Demo data represents the latest released database, not `master`. Do not adapt it to development
  schema changes. Implement development migrations in the applicable update scripts and preserve
  the upgrade path from the latest release.
- Run checks appropriate to the change and review the complete diff, including `git diff --check`.
  Report only checks actually executed and disclose material validation gaps.
- Do not commit without an explicit request. Before committing, show the complete patch and proposed
  message and obtain explicit approval. Do not add co-author trailers.

## Task-specific skills

Read the linked skill when the task touches its subject; load additional skills only as needed.
These local paths also work when automatic skill discovery is unavailable.

| Task | Instructions |
| --- | --- |
| Native modules, presenters, services, HTTP workflows | [admidio-native-development](.agents/skills/admidio-native-development/SKILL.md) |
| Forms, controls, templates, UI text | [admidio-ui-guidelines](.agents/skills/admidio-ui/SKILL.md) |
| SQL, schema migrations, entities, preferences | [admidio-database](.agents/skills/admidio-database/SKILL.md) |
| Changelog integration and audit behavior | [admidio-changelog](.agents/skills/admidio-changelog/SKILL.md) |
| Hooks and entity persistence events | [admidio-hooks](.agents/skills/admidio-hooks/SKILL.md) |
| CLI commands and bootstrap boundaries | [admidio-cli](.agents/skills/admidio-cli/SKILL.md) |
| Plugin implementation and lifecycle | [admidio-plugins](.agents/skills/admidio-plugins/SKILL.md) |
| Regression tests and database test execution | [admidio-testing](.agents/skills/admidio-testing/SKILL.md) |
| Commits and format-patch delivery | [admidio-git-delivery](.agents/skills/admidio-git-delivery/SKILL.md) |
