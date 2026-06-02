# Training Environment Test Matrix

End-to-end validation that every `make` class slug deploys and provisions on a
current `Percona-Training` AMI, then tears down cleanly. Tracking issue: #65.

## Method

- **Region:** `us-west-2`, **teams=1** per architecture.
- **Flow per slug:** `make setup` → (auto-detect newest AMI) → launch → wait for
  SSH → `ansible-playbook hosts.yml` → verify recap → `make teardown`.
- **AMI:** auto-detected newest — `Percona-Training-20260319` (`ami-019d664eea084aba9`).
- **Date:** 2026-06-02.
- Slugs are grouped by **distinct architecture** (many slugs share a machine set),
  so each shape is exercised once.

## Results — L1 (deploys + provisions clean, then tears down)

| Architecture | Slug(s) covered | Result | Ansible recap (failed=0 everywhere) |
| --- | --- | --- | --- |
| `db1` | `mysql-dev`, `mysql-101`, `mysql-oracle-dba`, `proxysql`, `pg-ops`, `pg-dev`, `pg-tutorial` | ✅ PASS | db1 ok=9 |
| `db1` + `db2` | `mysql-ops` | ✅ PASS | db1 ok=9, db2 ok=10 |
| `node1` | `mysql-k8s` | ✅ PASS | node1 ok=15 |
| `pxc` (app+mysql1/2/3) | `pxc` | ✅ PASS | app=11, mysql1=16, mysql2=12, mysql3=12 |
| `gr` (app+mysql1/2/3) | `gr`, `gr-101` | ✅ PASS | app=11, mysql1=16, mysql2=12, mysql3=12 |
| `mongodb` | `mongo-ops`, `mongo-dev` | ✅ PASS | mongodb ok=14 |

All teardowns verified: **0 instances / 0 VPCs** left after the run.

## Bugs found and fixed (this PR)

1. **`make list-amis` errored** — it invoked a `LISTAMIS` action that did not
   exist. Added a real `LISTAMIS` action (region-only).
2. **`make setup` never launched** — AMI auto-detection ran
   `grep "AMI" | grep -v 'Name' | head -n 1`, which matched the prose line
   *"You must set the AMI (-i)..."* and returned the word `instances.`, failing
   the `ami-*` check every time. Switched to `LISTAMIS | grep -oE 'ami-…' | tail -1`.
3. **`make setup` ran Ansible too early and hid failures** — no wait for sshd
   (Ansible `UNREACHABLE`), and the script reported "Setup complete!" / exit 0
   even when Ansible failed. Added a per-host SSH-readiness poll and exit-code
   propagation.

## Notes / follow-ups

- `pg-ops` / `pg-dev` / `pg-tutorial` currently map to the **MySQL `db1`** machine
  type (not PostgreSQL). The real PostgreSQL topology (`pg1/pg2/pg3`) is in #51.
- `make teardown` prompts for interactive `y/n` confirmation (a safety guard);
  for automation pipe `yes |`.
- DynamoDB region configurability (#52/#61) and the `TAG` action (#53/#62) are
  validated under their own PRs.
