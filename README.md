# Percona Training AWS Scripts

Automated provisioning scripts and Ansible playbooks used to deploy ephemeral AWS environments for Percona's training classes.

This repository provides tools for instructors to quickly launch, configure, and tear down realistic database environments (MySQL, MongoDB, PostgreSQL) for students.

## Prerequisites

To run these scripts, your local control machine requires:
* **PHP 8.5+**
* **Composer** (for PHP dependencies)
* **Ansible Core**
* **AWS CLI** (configured with proper credentials in `~/.aws/credentials`)
* **Make**

### Installation

**macOS (Homebrew):**
```bash
brew install php@8.5 ansible awscli composer make
```

**Linux (Ubuntu/Debian):**
```bash
sudo apt-get install php8.5 php-xml php-mbstring ansible awscli composer make
```

**Linux (Rocky Linux/RHEL 9):**
```bash
sudo dnf install php php-xml php-mbstring ansible-core awscli composer make
```

After installing the system packages, install the PHP dependencies:
```bash
composer install
```

---

## Setting Up a Training Environment

The standard workflow utilizes a `Makefile` that encapsulates VPC creation, instance launching, and Ansible provisioning into a single command. 

Environments are built based on **Class Slugs**. A slug represents the specific course being taught and automatically deploys the correct architecture (e.g., `db1`, `gr`, `pxc`).

### 0. List Available AMIs

Before launching, you can check which AMIs are available in your target region:

```bash
make list-amis region=eu-west-1
```

### 1. Launch the Environment

Run the `make setup` command, providing the class slug, your client identifier, the number of student teams, and optionally the AWS region (defaults to `us-west-2`).

```bash
make setup class=mysql-dev client=TREK teams=14 region=eu-west-1
```

*Note: All created resources (Instances, VPCs, Subnets) are automatically tagged with a `TrainingEndDate` set to 7 days from creation to ensure automated cleanup and cost control.*

### 2. Distribute Connection Details

Once setup is complete, generate the student connection summary:

```bash
make summary client=TREK
```

This will output a formatted block of text containing the S3 dashboard URL (which lists all instance IPs), the standard SSH user (`ec2-user` or `rocky`), and instructions for downloading the SSH keys. Share this output with your class.

### 3. Teardown

After the class concludes, destroy all resources to stop AWS billing:

```bash
make teardown client=TREK region=eu-west-1
```

---

## Supported Courses and Slugs

Use the following slugs with the `class=` parameter in your `make setup` command.

### MySQL Courses
| Course Title | Class Slug | Architecture Deployed |
| :--- | :--- | :--- |
| MySQL Training for Database Operations Specialists | `mysql-ops` | `db1` (Master), `db2` (Replica) |
| MySQL Training for Developers | `mysql-dev` | `db1` |
| DBA Hands-On (MySQL 101) | `mysql-101` | `db1` |
| MySQL for Oracle DBA's | `mysql-oracle-dba` | `db1` |
| ProxySQL Tutorial | `proxysql` | `db1` |
| Percona Operator for MySQL based on PXC | `mysql-k8s` | `node1` (K8s node) |
| Percona XtraDB Cluster Tutorial | `pxc` | `pxc` (3 nodes + 1 app) |
| MySQL Group Replication 101 | `gr-101` | `gr` (3 nodes + 1 app) |
| Percona Group Replication Tutorial | `gr` | `gr` (3 nodes + 1 app) |

### MongoDB Courses
| Course Title | Class Slug | Architecture Deployed |
| :--- | :--- | :--- |
| MongoDB Training for Database Operations Specialists | `mongo-ops` | `mongodb` |
| MongoDB Training for Developers | `mongo-dev` | `mongodb` |

### PostgreSQL Courses
| Course Title | Class Slug | Architecture Deployed |
| :--- | :--- | :--- |
| PostgreSQL Training for Database Operations Specialists | `pg-ops` | `db1` |
| PostgreSQL Training for Developers | `pg-dev` | `db1` |
| PostgreSQL Tutorial | `pg-tutorial` | `db1` |

---

## Technical Details

The provisioning scripts and Ansible playbooks support the following software and configurations:

*   **Operating Systems:** Ubuntu 22.04+, Debian 11+, Rocky Linux 9+.
*   **Database Versions:** Percona Server for MySQL 8.0, 8.4; Percona Server for MongoDB 7.0; and PMM 3.x client support.
*   **Security:** IPTables is disabled by default to simplify lab networking.
*   **SSL:** All MySQL instances are configured with SSL (SHA256).

---

## Advanced Usage

For custom deployments or debugging, you can bypass the `Makefile` and use the underlying PHP scripts directly.

### VPC Management
```bash
./setup-vpc.php -a [ADD|DROP|LIST|REBUILD] -r [REGION] -p [CLIENT_SUFFIX]
```

### Instance Management
```bash
./start-instances.php -a ADD -r [REGION] -p [CLIENT_SUFFIX] -c [NUM_TEAMS] -m [MACHINE_TYPE] -i [AMI_ID]
```

*   If you omit `-i [AMI_ID]`, the script will output a list of available `Percona-Training` AMIs in that region.
*   To launch multiple machine types simultaneously, separate them with commas: `-m db1,db2`.
*   Use `-o [OFFSET]` to add additional teams without overlapping existing numbers.

### CloudFormation Templates
Older iterations of the training labs (PMM, some PXC/GR setups) utilized pure AWS CloudFormation. These templates are retained in the `cloudformations/` directory for legacy support and reference.

---

## DynamoDB Requirement

The scripts rely on an AWS DynamoDB table to sync and list the generated IPs for the student dashboard.

*   **Region:** Must be in `us-east-1` (hardcoded).
*   **Table Name:** `percona_training_servers`
*   **Partition Key:** `teamTag` (String)
*   **Sort Key:** `teamID` (Number)

*You generally do not need to manage this table. The scripts will create or update it automatically.*

---

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines on code style, linting, and submitting Pull Requests. See [CONTRIBUTORS.md](CONTRIBUTORS.md) for a list of project maintainers.
