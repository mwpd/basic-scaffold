# CLAUDE.md

The rules for working in this repo are agent-agnostic and live in one place, so
this file only points at them.

@AGENTS.md

Repo-local skills live in `.agents/skills/` and are symlinked into
`.claude/skills/`:

- **`github`**: load it before filing, triaging, picking up or closing work.
- **`triage-issues`**: prepare open issues for unattended work.
- **`work-issue`**: take one pickable issue through to a merged PR.
