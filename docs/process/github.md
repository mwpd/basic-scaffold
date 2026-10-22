# Working through GitHub

The issue tracker is where this repository's planned work lives: not a
markdown file, not a TODO comment, not a session transcript. This page is the
working agreement: what each label means, how an issue becomes ready, and what
an agent does before and after writing code.

The `github` skill (`.agents/skills/github/SKILL.md`) has the commands. The
`triage-issues` and `work-issue` skills run the two halves of the loop.

## Where a thing belongs

| It is… | It lives in… |
| --- | --- |
| Planned work, of any size | A GitHub issue |
| A theme that needs several issues | An epic issue, with native sub-issues |
| Progress on work in flight | Comments on the issue, and the PR |
| A security vulnerability | A private security advisory, never a public issue |

## Labels

The full list with descriptions is `.github/labels.json`. Edit it there and
sync with `php scripts/sync-labels.php` (dry run) or `--apply`. Never create a
label by hand.

- **`type:`**: `feat` `fix` `refactor` `test` `docs` `chore` `spike`.
  **Exactly one per issue.**
- **`area:`**: where it lands. `bootstrap`, `infrastructure`, `sample`,
  `tests`, `tooling`, `ci`, `docs`. One or more.
- **`priority:`**: `p0` drop everything, `p1` next up, `p2` wanted but
  unscheduled, `p3` someday. **No priority label means unsorted**, which is
  an honest state.
- **`needs:`**: what blocks it when the blocker is not another issue:
  `decision`, `design`, `repro`, `human`, `use-case`.
- **Meta**: `epic`, `agent-ready`, `blocked`, `breaking`, `security`, plus
  GitHub's triage set (`good first issue`, `help wanted`, `question`,
  `duplicate`, `wontfix`).

Labels that carry weight beyond their description:

- **`agent-ready`** means a coding agent can take the issue unattended: context
  with real file paths, an explicit out-of-scope section, and acceptance
  criteria someone else could check. Never on an epic. Never alongside a
  `needs:` label.
- **`breaking`** means the change alters something generated plugins rely on:
  an interface or base class in `src/Infrastructure/`, a hook or service
  identifier convention, or the bootstrap contract. It needs a maintainer
  ruling on the issue before code is written.
- **`needs: human`** means the next action can only be performed by a human,
  such as granting access or a verification reserved for the maintainer. Put the
  exact action and what evidence completes it in the issue body, and remove
  `agent-ready` in the same edit.
- **`blocked`** means the issue waits on another one. Name it on its own
  unindented line in the body, `Blocked by #42`, and remove the label by hand
  when the blocker closes.

## Workflow state

There is no project board and no status labels. State is read from facts
GitHub already records:

| State | How you can tell |
| --- | --- |
| Unsorted | Open, no `priority:` label |
| Not ready | Open, any `needs:` label, or no `agent-ready` |
| **Pickable** | Open, `agent-ready`, one `priority:`, unassigned, no `blocked`, no `needs:`, not an epic, no open PR referencing it |
| In progress | Assigned, usually with a claim comment naming the branch |
| In review | An open PR says `Closes #N` |
| Done | Closed |

An agent picking work takes the pickable issue with the highest priority, then
the oldest.

## The lifecycle

1. **File.** Use a template. An issue with no acceptance criteria is not ready,
   whatever else it has.
2. **Triage.** One `type:`, at least one `area:`, a priority, and the honest
   `needs:` labels. Add `agent-ready` only when a stranger could implement it
   from the body alone.
3. **Claim.** Assign yourself and comment the branch name:
   `<type>/<issue-number>-<slug>`, e.g. `fix/2-loop-service-header`.
4. **Review.** Open the PR with `Closes #N` when merging meets every acceptance
   criterion; otherwise `Refs #N` and name the gap under "Anything left open".
   CI must be green on the current head.
5. **Done.** The merge closes the issue. An epic closes when its last sub-issue
   does *and* its definition of done is met.

An issue that turns out to be wrong gets closed with a comment saying why.
`wontfix` is a legitimate outcome.

Between those steps the issue stays true: its body is the current
specification, and its comments are the history. Fold corrections into the body
with a dated note rather than leaving a reader to reconcile the thread.

## What an agent does before writing code

1. **Read the issue, its epic, and every link in both.**
2. **Check it against the [design principles](../design-principles.md).** A
   task that contradicts one was filed wrong: say so on the issue and add
   `needs: decision` rather than implementing it.
3. **Confirm the context is still true.** Issue bodies cite `file:line`. Code
   moves. Verify before building on a citation, and correct the issue if it has
   drifted.
4. **Work the acceptance criteria, not the title.** A fix's test is shown
   failing before the change.
5. **Stay inside the scope.** Something genuinely necessary that the issue did
   not anticipate gets a comment, and its own issue if it is more than a line.
6. **Comment what changed about the plan**, not what you did. The diff says
   what you did.

## Pull requests from forks

A contributor without write access opens the PR from a fork, and it stays the
PR of record. CI runs on fork PRs with read-only permissions. GitHub may hold
a first-time contributor's run until a maintainer approves it, and a held run
is not a passing check.

## In plugins generated from this scaffold

A repository generated from this template inherits `.github/`, `AGENTS.md` and
the skills. The process carries over unchanged, and the skills target whichever
repository they run in. But `.github/labels.json`
describes *this* scaffold's areas. Rename the `area:` labels to fit the new
plugin, then run `php scripts/sync-labels.php --apply` in the new repository.
