---
name: work-issue
description: Take one unassigned, highest-priority agent-ready issue in this repository through readiness check, implementation, PR and merge, or a documented handoff. Use when asked to pick up and complete an issue; not for triage or listing.
---

# Work one issue

One invocation handles **one claimed issue**. Finish when its PR is merged, or
when its blocker is documented and the claim released. Do not take the next
issue afterwards.

An end-to-end request authorizes assignment, issue updates, scoped
implementation, commits, push, PR and merge. Honor narrower instructions.
Loading this skill does not by itself authorize starting.

Read [the GitHub skill](../github/SKILL.md), `AGENTS.md` and
`docs/process/github.md` first. Set `$REPO` as the GitHub skill shows.

## Select and claim

1. Fetch `origin/main`. Use the GitHub skill's "Picking up work" query. If a
   specific issue was named, use that one but still check it qualifies.
2. Skip any candidate with an open PR that references it, or recent comments
   showing someone working on it.
3. Re-read the candidate, then claim it:
   `gh issue edit N --repo "$REPO" --add-assignee @me`. Read the
   assignment back. If someone else appeared, stop.
4. Comment the claim and the intended branch, `<type>/<N>-<slug>`.

## Establish readiness

Read the issue, all comments, its epic and links. Verify every `file:line`
citation against the fetched source. Repair drift that the source settles,
folding corrections into the body with a dated note.

If a real gap remains (a missing ruling, design, reproduction, or human
action), apply the matching `needs:` label with a comment naming what is
missing and what would resolve it, remove `agent-ready`, and go to **Finish
or hand off**. Do not invent a design or a ruling to keep moving.

## Implement

Work in a checkout used by nothing else. If the current one is dirty or
shared, create a worktree from `origin/main` on the issue branch. Never stash,
reset or clean someone else's work.

- Work the acceptance criteria one at a time. For a fix, write the test first
  and record it failing before the change.
- Stay inside the scope. A necessary unplanned change gets a comment on the
  issue; anything bigger than a line gets its own issue via the GitHub skill.
- Commit in reviewable steps with plain imperative messages.
- Run `composer test` and `composer lint` before pushing. Never weaken a gate,
  add a baseline or delete a test to get green.

## PR and merge

Open one PR against `main` from `.github/pull_request_template.md`, with the
issue's `type:` and `area:` labels. Use `Closes #N` only when every acceptance
criterion is met; otherwise `Refs #N` with the gap named.

Watch CI on the **current head SHA**. Fix real failures with new commits;
do not rerun a genuine failure hoping for green. Address review comments.
Once CI is green and nothing is unresolved, merge:

```sh
gh pr merge <pr> --repo "$REPO" --squash --match-head-commit <sha>
```

## Finish or hand off

- **Merged**: confirm the issue closed, and clear `blocked` from dependants
  per the GitHub skill. Post a one-line outcome with the PR link if the merge
  did not make it obvious.
- **Blocked**: keep partial commits on the pushed branch or a draft PR. Post
  the blocker, what remains and how to resume. Then remove only your own
  assignment: `gh issue edit N --repo "$REPO" --remove-assignee @me`.

Remove a worktree you created only when it has no uncommitted work. Report the
issue, the outcome, links, and any unfinished tracker action. Do not start
another issue.
