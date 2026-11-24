---
name: admidio-git-delivery
description: Prepare explicitly requested Admidio commits or git format-patch series with replay validation.
---

# Git delivery

Inspect status, branch, baseline commit and unrelated changes. Preserve the user's tree. Before
committing, show the full patch and proposed message and obtain explicit approval; approval does
not authorize later commits. Do not stage unrelated work.

Commit one coherent issue at a time, independently reviewable, even when issues touch the same file.
Use repository-style `Prefix: describe the change`, imperative, with at most one or two context
sentences. Do not add co-author or similar trailers.

For requested patch series, record the exact baseline and dependency order. Generate standard
`git format-patch` output, replay using `git am` in a clean worktree at the baseline, and run
`git diff --check`, syntax and relevant tests on the replayed tree. Provide checksums and precise
validation limitations. Exclude unrelated conversions, fixtures and formatting.

Inspect `.gitattributes`, local Git settings and actual bytes before newline-sensitive edits; do not
assume universal CRLF. Review the full diff for normalization, secrets, local config, uploads and
generated output. Deliver complete changes and name actual pattern files and checks without
placeholder APIs or speculative claims.
