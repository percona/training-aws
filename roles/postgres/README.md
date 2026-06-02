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

## The provisioning sequence — use `make`

`make` is the canonical interface (see `make help`). The PostgreSQL class slug is
**`pg-ops`** (`pg-dev`/`pg-tutorial` are aliases). One `make setup` does the whole thing:
creates the VPC, launches one `pg` set (pg1/pg2/pg3) per team **plus the shared PMM
server**, builds the Ansible inventory, and provisions everything. Do this **the day
before class**, not the morning of.

Example: client tag `ACME`, 8 students, default region `us-west-2`:

| # | Step | Command | ~Time |
| - | ---- | ------- | ----- |
| 1 | (Optional) find the current training AMI | `make list-amis` | — |
| 2 | **Provision the whole class** — VPC + pg sets + shared PMM + Ansible | `make setup class=pg-ops client=ACME teams=8` | ~15–20 min |
| 3 | Print the connection sheet / dashboard URL | `make summary client=ACME` | — |
| 4 | Smoke-test (below) | — | ~5 min |
| — | **Tear down after class** | `make teardown client=ACME` | ~3 min |

Add `region=<aws-region>` to any target to use a non-default region (e.g.
`make setup class=pg-ops client=ACME teams=8 region=eu-west-1`).

> **What `make setup` wires up for you (no manual steps):** the VPC gets the intra-SG
> self-reference rule the HA labs need; `hosts.yml` runs the `pmm` play first and waits for
> PMM to be ready; and the pg play **auto-derives `pmm_server_ip` from the inventory**, so
> the `pg` nodes register against the shared PMM server with no IP to copy by hand.

<details>
<summary>Under the hood (what <code>make setup</code> runs, if you ever need to drive it by hand)</summary>

`make setup class=pg-ops …` calls `setup-class.sh`, which runs:

```bash
./setup-vpc.php       -a ADD -r <region> -p ACME
./start-instances.php -a ADD -r <region> -p ACME -c 8 -m pg   -i <ami>   # per-team sets
./start-instances.php -a ADD -r <region> -p ACME -c 1 -m pmm  -i <ami>   # shared PMM
./start-instances.php -a GETANSIBLEHOSTS -r <region> -p ACME > ansible_hosts_ACME
ansible-playbook -i ansible_hosts_ACME hosts.yml                          # pmm_server_ip auto-derived
```
`make teardown client=ACME` runs the `DROP` actions for the instances **and** the VPC.
</details>

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
