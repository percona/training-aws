# Stand up the PostgreSQL-17 training environment — Start Here

**You know AWS/Ansible basics but nothing about *this* class's setup. Read this one page
first.** It gives you the provisioning sequence, how long each step takes, and what to
verify — then fans out to the detail.

> This builds the environment for the **Percona PostgreSQL DBA & Operations** course. The
> course content (slides, labs, instructor docs) lives in `pg-training/` in the
> **`training-material`** repo; the deeper provisioning write-up is
> [`pg-training/infra/README.md`](../../../training-material/pg-training/infra/README.md).

---

## What you're building

Per **student**: three EC2 nodes — `pg1` (primary + sysbench client), `pg2` (standby),
`pg3` (pgBackRest repo / DR / 3rd Patroni node / HAProxy+PgBouncer). Per **class**: one
shared `pmm` server. All run Percona Distribution for PostgreSQL 17 on the EL9 training
AMI. The `pg` alias expands to one full `pg1,pg2,pg3` student set.

This `postgres` role brings each node up to a clean, **primary/standby-ready PG 17
cluster** — Patroni/etcd/HAProxy/PgBouncer are installed *during the Block 3 labs*, not
pre-baked (standing them up is the point of those labs).

---

## The provisioning sequence

Run from your `training-aws` checkout. Example: client tag `ACME`, region `us-west-2`, 8
students. Do this **the day before class**, not the morning of.

| # | Step | Command | ~Time |
| - | ---- | ------- | ----- |
| 1 | Create/refresh the VPC (auto-adds the intra-SG self-reference rule the HA labs need) | `./setup-vpc.php -a ADD -r us-west-2 -p ACME` | ~1 min |
| 2 | Launch the shared PMM server | `./start-instances.php -a ADD -r us-west-2 -p ACME -c 1 -m pmm -i <ami>` | ~2 min to boot |
| 3 | Launch one `pg` set per student | `./start-instances.php -a ADD -r us-west-2 -p ACME -c 8 -m pg -i <ami>` | ~2–3 min to boot |
| 4 | Wait for SSH, then build the inventory | `./start-instances.php -a GETANSIBLEHOSTS -r us-west-2 -p ACME > ansible_hosts_acme` | — |
| 5 | Provision (PMM play runs first, then the pg nodes register to it) | `ansible-playbook -i ansible_hosts_acme hosts.yml -e pmm_server_ip=<pmm-private-ip>` | ~10–15 min |
| 6 | Smoke-test (below) | — | ~5 min |
| — | **Tear down after class** | `./start-instances.php -a DROP -r us-west-2 -p ACME -i <ami>`, then delete the VPC | ~3 min |

> **Order matters in step 5:** `hosts.yml` runs the `pmm` play before the `pg` play and
> waits for PMM to be ready, so the clients can register. Always pass `-e pmm_server_ip=`.

---

## Verify before you walk away (smoke test)

On any student's `pg1` (as `postgres`):

```sql
SELECT count(*) FROM bluebox.film;        -- expect 7836 — the dataset loaded
SHOW listen_addresses;                     -- '*' — remote connections allowed
SELECT 1 FROM pg_roles WHERE rolname='replicator';   -- exists — replication labs ready
```

And from the shell: `systemctl is-active postgresql-17` → `active`; the `pmm` server's web
UI is reachable on `https://<pmm-public-ip>` (port 443). If `bluebox.film` is empty or the
`postgres` password isn't set, re-run the `pg` play — those are the two things that bite.

---

## Fan out to the detail

- **[`pg-training/infra/README.md`](../../../training-material/pg-training/infra/README.md)**
  — the full provisioning write-up: topology rationale, `hosts.yml` additions, the two-pass
  SSH-key note for pgBackRest, connection sheet/keys, what the role installs.
- **[`tasks/main.yml`](tasks/main.yml)** — the exact install/config sequence on every pg node.
- **[`tasks/pg1.yml`](tasks/pg1.yml)** — pg1-only extras (Bluebox dataset, `replicator`/`sysbench` roles, PostGIS).
- **[`tasks/pmm.yml`](tasks/pmm.yml)** — the shared PMM server setup.
- **[`defaults/main.yml`](defaults/main.yml)** — passwords and tunables (keep in sync with the labs).
- **Per-AMI gotchas** (package names, etcd, sysbench path) — `INSTRUCTOR-NOTES.md` in the course repo.
- **Proof it works** — `LAB-VALIDATION-REPORT.md` in the course repo records a full end-to-end run.

> The top-level [`README.md`](../../README.md) "machine types" section covers the `pg`/`pmm`
> aliases and the security-group rule in the context of all the training machine types.
