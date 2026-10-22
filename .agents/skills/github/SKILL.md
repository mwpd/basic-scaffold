---
name: github
description: Use when filing, triaging, picking up, updating or closing work in this repository's issue tracker, including creating epics and sub-issues, choosing labels, opening a PR against an issue, and recording found work mid-session.
compatibility: Requires the `gh` CLI, authenticated with repository access, and `jq`.
---

# Working the issue tracker

**This skill is the procedure. [`docs/process/github.md`](../../../docs/process/github.md)
is the agreement it implements.** Read that first if you are deciding
*whether* something should be an issue. Read this when you are about to run a
command.

Commands below use `$REPO`, the repository of the current checkout, so they
also work unchanged in a plugin generated from this template:

```sh
REPO=$(gh repo view --json nameWithOwner --jq .nameWithOwner)
```

## Filing an issue

Write the body to a file and pass `--body-file`. Inline `--body` mangles
backticks and newlines through the shell.

```sh
gh issue create --repo "$REPO" \
  --title "infrastructure: resolve delayed services in registration order" \
  --body-file /tmp/issue-body.md \
  --label "type: fix" --label "area: infrastructure" --label "priority: p2"
```

The body follows `.github/ISSUE_TEMPLATE/task.yml`: **Context** (with real
`file:line` pointers), **Scope**, **Out of scope**, **Acceptance criteria**,
**Verification**, and **Impact on generated plugins**. An issue missing any of
those is not `agent-ready`.

Four rules decide whether the issue is still usable six weeks from now:

1. **Cite the code.** `src/Infrastructure/ServiceBasedPlugin.php:253`, not "the
   service registration". A reader should never start by searching.
2. **Write the out-of-scope section first.** It is the section that stops a
   one-file change becoming a refactor.
3. **Make every acceptance criterion checkable by someone else.** "Works
   correctly" is not one. "A delayed service whose dependency is also delayed
   registers after it, proven by a test that fails before the change" is.
4. **Title it `<area>: <what is wrong or wanted>`.** The type is a label, not a
   title prefix. An epic is `Epic: <outcome>`.

When the work waits on another issue, put `Blocked by #42` on its own
unindented line in the body and add the `blocked` label.

## Epics and sub-issues

An epic holds the context its tasks would otherwise repeat and is never coded
directly. Create it from `.github/ISSUE_TEMPLATE/epic.yml`, then attach tasks
through the REST API, which wants the sub-issue's **database id**, not its
number:

```sh
SUB_ID=$(gh api "repos/$REPO/issues/42" --jq .id)
gh api -X POST "repos/$REPO/issues/7/sub_issues" -F sub_issue_id="$SUB_ID"
gh api "repos/$REPO/issues/7/sub_issues" --jq '.[] | "\(.number) \(.title)"'
```

One parent per issue. "Part of #7" in prose does not attach it; the API call
does. Attach an issue only when its deliverable advances the epic's outcome,
not merely because it was discovered during the epic's work.

## Labels

The taxonomy is `.github/labels.json`, pushed with
`php scripts/sync-labels.php` (dry run) and `--apply`. **Never create a label
by hand**, because the next sync will not know it exists.

Exactly one `type:`. At least one `area:`. A `priority:` only if it is actually
sorted: an unprioritised issue is honest, a guessed `p2` is not.

Use `needs:` labels for blockers that are not another issue:

- `needs: decision`: post the exact question, the options, their
  consequences, and a recommendation kept separate from the approval.
- `needs: design`: the issue waits on **visual** design (a user interface,
  the project website, graphics). Post what is decided, what is left, and the
  specific visual questions to answer. Never use it for software design.
- `needs: repro`: record what was tried and what evidence is still missing.
- `needs: human`: put the exact human action and its completion evidence in
  the body, and remove `agent-ready` in the same edit.
- `needs: use-case`: name the scenario that would justify the work.

### Unsettled software design

When the remaining question is how an API, an interface or the
orchestration should look, the next step is a **design proposal**, not a
`needs:` label. Keep the issue `agent-ready` and say in the body that the next
deliverable is a proposal. The proposal is a comment on the issue with:

- what is already decided, with links to the rulings and the design
  principles that bind it;
- the options, each with its trade-offs and how it fits the design principles;
- a recommendation, kept separate from approval.

After posting it, add `needs: decision`, remove `agent-ready`, and stop: an
agent that implements its own proposal before the ruling has invented a
decision. When the maintainer rules, record the decision (see "Keeping an
issue true"), fold it into the body, and restore `agent-ready`.

## Picking up work

Run it under `set -o pipefail`: otherwise a failed `gh` call (auth, network,
rate limit) reaches `jq` as empty input and reads as "nothing to pick".

```sh
set -o pipefail
gh api --paginate --slurp "repos/$REPO/issues?state=open&labels=agent-ready&per_page=100" \
  | jq '[.[][]
    | select(.pull_request == null and (.assignees | length) == 0)
    | [.labels[].name] as $l
    | select(($l | index("epic")) == null and ($l | index("blocked")) == null)
    | select(all($l[]; startswith("needs:") | not))
    | [$l[] | select(startswith("priority:"))] as $p
    | select(($p | length) == 1)
    | {number, title, priority: $p[0], created_at}]
    | sort_by(.priority, .created_at)'
```

The list is candidates, not proof. Check open PRs for one already referencing
the issue before claiming it. Then:

1. Read the issue, its epic, and every link in both.
2. **Verify the `file:line` citations still point at what the issue claims.**
   Correct the body if they have drifted.
3. Work the acceptance criteria one at a time. A fix's test is shown failing
   before the change.

Branch as `<type>/<issue-number>-<slug>`, e.g. `fix/2-loop-service-header`.

## Keeping an issue true

- **Fold corrections into the body** in the same sitting, ending with a dated
  line saying what changed. Do not leave the reader to reconcile the thread.
- **Record a ruling where it unblocks.** Comment
  `**Decision (maintainer, <date>):** …`, point the acceptance criteria at the
  chosen option, remove the `needs:` label, and add `agent-ready` if the brief
  is now complete.
- **Clear `blocked` by hand when the blocker closes.** Nothing automates it.
  Find dependants with
  `gh issue list --repo "$REPO" --state open --label blocked --search '"#42" in:body'`
  and read each hit before removing the label.
- **Keep one of two duplicates.** Move anything the other has into the
  survivor's body, then close the other pointing at it.

## Closing it

Before opening a PR, run `composer test` and `composer lint`.

```sh
gh pr create --repo "$REPO" --title "Fix loop service header on archive pages" \
  --body-file /tmp/pr-body.md --label "type: fix" --label "area: sample"
```

The body follows `.github/pull_request_template.md` and says `Closes #N` only
if every acceptance criterion is met; otherwise `Refs #N` and name the gap under
"Anything left open". `gh pr create` does not copy labels from the issue, so
pass `type:` and `area:` explicitly. Do not copy `agent-ready` or `priority:`
onto a PR.

Merge only with CI green on the current head:
`gh pr merge N --repo "$REPO" --squash --match-head-commit <sha>`.

After the merge, clear `blocked` from any issue that named this one.

## Filing found work mid-session

Something real, out of scope and about to be forgotten gets an issue before the
session ends. Say what was **observed**, where, and why it matters, not what
you suspect. No acceptance criteria yet? Then it is a note: say "needs scoping"
and leave `agent-ready` off.
