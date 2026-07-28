# Learnomancer

Learnomancer is a PHP CLI for provisioning and tearing down Percona training environments in AWS. It creates VPCs, launches EC2 instances for classes, generates Ansible inventory, and SSH config files.

The CLI entry point is `./bin/learnomancer`, built on [CakePHP Console](https://book.cakephp.org/5/en/console-commands.html).

```bash
$ ./bin/learnomancer
Available Commands:
  ansible-config     Generate an Ansible configuration file for a given class
  create-class       Create a new class, including the VPC, instances, and Ansible configuration
  create-instances   Create, or add, new instances to a existing class
  create-vpc         Create a VPC for a new class
  drop-class         Drop an existing class, including the VPC, instances, and related resources
  drop-instances     Drop instances from an existing class
  drop-vpc           Drop the VPC for an existing class
  list-amis          List available AMIs for a given region
  list-instances     List currently running instances for an existing class
  list-vpcs          List currently existing VPCs for a given region
  ssh-config         Generate an SSH config file for an existing class
  summary            Output summary of a given class, including the class dashboard, and SSH info
  sync-dynamo        Sync instance information to DynamoDB (rarely needed)
  vpc-config         Rebuild the local Learnomancer config for a given class (rarely needed)
```

---

## Quick Start

1. Install requirements, and clone or update local repo
2. Login to AWS via SSO, if Key/Secret not previously configured
3. Create a class. See 'create-class' subcommand below.
4. Execute `ansible-playbook` against the class inventory
5. Get class dashboard URL. See 'summary' subcommand below.

## Requirements and Installation

### Requirements

- PHP 8.5+
- Composer
- AWS CLI configured with credentials that can manage EC2, VPC, and DynamoDB
- Ansible (for provisioning hosts after inventory is generated)

### Install with Homebrew

```bash
brew install php@8.5 composer ansible
git clone https://github.com/percona/training-aws
cd training-aws
composer install
```

Ensure `php` is in your `PATH`:

```bash
$ php -v
PHP 8.5.3 (cli) (built: Feb 10 2026 18:25:51) (NTS)
Copyright (c) The PHP Group
Built by Homebrew
Zend Engine v4.5.3, Copyright (c) Zend Technologies
    with Zend OPcache v8.5.3, Copyright (c), by Zend Technologies
```

### AWS credentials

Configure `~/.aws/credentials` (or equivalent via environment / SSO):

```bash
[default]
aws_access_key_id = ...
aws_secret_access_key = ...
```

You can also use `aws sso login`

### Verify the CLI

```bash
./bin/learnomancer
./bin/learnomancer list-amis --help
```

### Slug and Region conventions

Use a short client **slug** (e.g. `ACME`, `TREK`) as the class suffix. Resources are named like `Percona-Training-{slug}`. Pick an AWS **region** close to the students (e.g. `us-west-2`, `eu-west-1`).

---

## Sub-commands

### `list-amis`

List Percona Training AMIs in a region.

#### Syntax

```bash
./bin/learnomancer list-amis <region>
```

#### Help

```bash
Usage:
learnomancer list_amis [-h] <region>

Options:

--help, -h  Display this help.

Arguments:

region  The region to list from
```

#### Example

```bash
$ ./bin/learnomancer list-amis us-west-2
========== Listing AMIs in 'us-west-2' ==========
+--------------------------------+-----------------------+
| Name                           | AMI Id                |
+--------------------------------+-----------------------+
| Percona-Training-20260301-AMI  | ami-0abc123def4567890 |
| Percona-Training-20260115-AMI  | ami-0fedcba9876543210 |
+--------------------------------+-----------------------+
```

---

### `list-instances`

List running instances for a class slug in a region.

#### Syntax

```bash
./bin/learnomancer list-instances <slug> <region>
```

#### Help

```bash
List currently running instances for an existing class

Usage:
learnomancer list_instances [-h] <slug> <region>

Options:

--help, -h  Display this help.

Arguments:

slug    The slug/suffix for the class
region  The region to list from
```

#### Example

```bash
$ ./bin/learnomancer list-instances ACME us-west-2
========== Listing instances for 'ACME' in 'us-west-2' ==========
+---------------------+--------------------------------------+----------------+
| InstanceId          | Hostname                             | PublicIpAddress|
+---------------------+--------------------------------------+----------------+
| i-0a1b2c3d4e5f67890 | Percona-Training-ACME-db1-T1         | 54.200.10.1    |
| i-0f9e8d7c6b5a43210 | Percona-Training-ACME-db2-T1         | 54.200.10.2    |
+---------------------+--------------------------------------+----------------+
```

---

### `list-vpcs`

List VPCs in a region

#### Syntax

```bash
./bin/learnomancer list-vpcs <region>
```

#### Help

```bash
Usage:
learnomancer list_vpcs [-h] <region>

Options:

--help, -h  Display this help.

Arguments:

region  The region to list from
```

#### Example

```bash
$ ./bin/learnomancer list-vpcs us-west-2
========== Listing VPCs in 'us-west-2' ==========
+------------------------+----------------------------+
| VpcId                  | Name                       |
+------------------------+----------------------------+
| vpc-0abc123def4567890  | Percona-Training-ACME-VPC  |
+------------------------+----------------------------+
```

---

### `create-vpc`

Create a VPC including subnet, gateway, route table, and security groups.

#### Syntax

```bash
./bin/learnomancer create-vpc <slug> <region>
```

#### Help

```bash
Usage:
learnomancer create_vpc [-h] [-r] <slug> <region>

Options:

--help, -h     Display this help.

Arguments:

slug    The slug/suffix for the class
region  The region to list from
```

#### Example

```bash
$ ./bin/learnomancer create-vpc ACME us-west-2
- No VPC Id in config file. Searching by name 'Percona-Training-ACME-VPC'...
- VPC 'Percona-Training-ACME-VPC' not found. Creating new VPC...
-- VPC Percona-Training-ACME-VPC (vpc-0abc123def4567890) created in us-west-2
- Checking VPC (vpc-0abc123def4567890) status...
-- VPC 'Percona-Training-ACME-VPC' is OK
...
```

---

### `drop-vpc`

Tear down the VPC and related networking for a class.

#### Syntax

```bash
./bin/learnomancer drop-vpc <slug> <region>
```

#### Help

```bash
Usage:
learnomancer drop_vpc [-h] <slug> <region>

Options:

--help, -h  Display this help.

Arguments:

slug    The slug/suffix for the class
region  The region to list from
```

#### Example

```bash
$ ./bin/learnomancer drop-vpc ACME us-west-2
- Dropping VPC for 'ACME' in 'us-west-2'...
-- Detaching and deleting internet gateway...
-- Deleting subnet, route table, security group...
-- Deleting VPC vpc-0abc123def4567890
- Done
```

---

### `create-instances`

Launch (or add) EC2 instances into an existing class.

Use this command to create/launch new instances for a class.
There are several machine types to select from. You can select a single
type, or combine several types separated by comma.

Types:

- db1: A regular PS 8.4 MySQL server with sample data
- db2: A blank instance. Typically used as a replica of db1.
- app: An instance with sysbench, and helper scripts for generating load
- mysql1, mysql2, mysql3: Regular PS 8.4 with sample data in S->R/R
configuration. Used in the PXC, and GR classes
- mongodb: Regular PS MongoDb server

Aliases:

- pxc: This is a combination alias for 4 types: "app,mysql1,mysql2,mysql3"
- gr: Also a combination alias for 4 types: "app,mysql1,mysql2,mysql3"

#### Syntax

```bash
./bin/learnomancer create-instances [-i <ami>] [-o <offset>] <slug> <region> <count> <machinetype[,machinetype...]>
```

#### Help

```bash
Usage:
learnomancer create_instances [-i ami-xxxxxx] [-h] [-o 0] <slug> <region> <count> <db1|db2|app|mysql1|mysql2|mysql3|pxc|gr|node1|node2|node3|node4|mongodb>

Options:

--ami, -i     The AMI to use. Use list-amis to get available AMIs
--help, -h    Display this help.
--offset, -o  Offset the team counter by this amount. Useful when adding
              more instances to an existing class.

Arguments:

slug         The slug/suffix for the class
region       The region to create into
count        The number of teams to create
machinetype  The machine type to create, related to the class
             (choices:
             db1|db2|app|mysql1|mysql2|mysql3|pxc|gr|node1|node2|node3|node4|mongodb)
             (separator: ",")

```

#### Example

```bash
# First pick the most recent date-based AMI
$ ./bin/learnomancer list-amis us-west-2

# Launch 9 teams of db1+db2 for ACME Co, MySQL Operations class
$ ./bin/learnomancer create-instances ACME us-west-2 9 db1,db2 -i ami-0abc123def4567890

# Later: add 2 more teams (offset past the existing 9)
$ ./bin/learnomancer create-instances -o 9 ACME us-west-2 2 db1,db2 -i ami-0abc123def4567890
```

---

### `drop-instances`

Terminate all instances for a class slug (does not drop the VPC).

#### Syntax

```bash
./bin/learnomancer drop-instances <slug> <region>
```

#### Help

```bash
Usage:
learnomancer drop_instances [-h] <slug> <region>

Options:

--help, -h  Display this help.

Arguments:

slug    The slug/suffix for the class
region  The region to list from
```

#### Example

```bash
$ ./bin/learnomancer drop-instances ACME us-west-2
- Found 18 instances for 'ACME' in 'us-west-2'
Continue? (y/n)
y
- Terminating instances...
- Done
```

---

### `create-class`

Primary command: create VPC, and launch instances for a known class type.

Available Classes, which should match slide material:

- MySQL:      mysql-ops, mysql-dev, pxc, gr, proxysql, mysql-k8s
- MongoDB:    mongo-ops, mongo-dev, mongo-k8s
- PostgreSQL: pg-ops, pg-dev, pg-k8s

#### Syntax

```bash
./bin/learnomancer create-class <class> <slug> <region> <count> <ami>
```

#### Help

```bash
Usage:
learnomancer create_class [-h] <mysql-ops|pxc|gr|proxysql|mysql-k8s|mongo-ops|mongo-dev|mongo-k8s|pg-ops|pg-dev|pg-k8s> <slug> <region> <count> <ami>

Options:

--help, -h  Display this help.

Arguments:

class   The name of the class. Use -h for full list. (choices:
        mysql-ops|pxc|gr|proxysql|mysql-k8s|mongo-ops|mongo-dev|mongo-k8s|pg-ops|pg-dev|pg-k8s)
slug    The slug/suffix for the class
region  The region to create into
count   The number of teams to create
ami     The AMI to use. Use list-amis to get a list default:
        "ami-xxxxxx"

```

#### Example

```bash
# List AMIs for region
$ ./bin/learnomancer list-amis us-west-2

# PostgreSQL Developers class for Skynet Inc, with 4 students:
$ ./bin/learnomancer create-class pg-dev SKY us-east-2 4 ami-0abc123def4567890

# MySQL Operations class for Acme Corp, with 9 students:
$ ./bin/learnomancer create-class mysql-ops ACME us-west-1 9 ami-0abc123def4567890
```

---

### `drop-class`

Use this command to drop an existing class based on slug and region.

#### Syntax

```bash
./bin/learnomancer drop-class <slug> <region>
```

#### Help

```bash
Usage:
learnomancer drop_class [-h] <slug> <region>

Options:

--help, -h  Display this help.

Arguments:

slug    The slug/suffix for the class
region  The region to create into

```

#### Example

```bash
./bin/learnomancer drop-class ACME us-west-2
```

---

### `ansible-config`

Generate Ansible inventory for a class. Print to stdout, or save with `-o`.

#### Syntax

```bash
./bin/learnomancer ansible-config [-o] <slug> <region>
```

#### Help

```bash
Usage:
learnomancer ansible_config [-h] [-o] <slug> <region>

Options:

--help, -h    Display this help.
--output, -o  Save output to file

Arguments:

slug    The slug/suffix for the class
region  The region to list from
```

#### Example

```bash
$ ./bin/learnomancer ansible-config -o ACME us-west-2
# Writes inventory file for the class to 'ansible_ACME'

# Use the ansible inventory config to prepare instances after creating the class
$ ansible-playbook -i ansible_acme hosts.yml
```

---

### `ssh-config`

Generate SSH `Host` entries file for class instances. Print to stdout, or save with `-o`. This file is typically only for the instructor in order to more quickly connect to a student's instance.

#### Syntax

```bash
./bin/learnomancer ssh-config [-o] <slug> <region>
```

#### Help

```bash
Usage:
learnomancer ssh_config [-h] [-o] <slug> <region>

Options:

--help, -h    Display this help.
--output, -o  Save output to file

Arguments:

slug    The slug/suffix for the class
region  The region to list from
```

#### Example

```bash
$ ./bin/learnomancer ssh-config ACME us-west-2
Host Percona-Training-ACME-db1-T1 db1-T1
  HostName 54.200.10.1
  User rocky
  IdentityFile Percona-Training.key
  StrictHostKeyChecking no
  ForwardAgent yes

$ ./bin/learnomancer ssh-config -o ACME us-west-2
# Writes ssh_ACME

# Now, you can easily connect to a student's instance for troubleshooting/assistance
$ ssh -F ssh_ACME db2-T3

```

---

### `summary`

Print the student dashboard URL, and connection reminders for a class. Share the dashboard URL with the students, and assign the team numbers at the beginning of one of the class sections.

#### Syntax

```bash
./bin/learnomancer summary <class-slug>
```

#### Help

```bash
Usage:
learnomancer summary [-h] <slug>

Options:

--help, -h  Display this help.

Arguments:

slug  The slug/suffix for the class
```

#### Example

```bash
$ ./bin/learnomancer summary ACME
=================================================================
                 TRAINING ENVIRONMENT READY
=================================================================
Server IP Dashboard: http://percona-training.s3-website-us-east-1.amazonaws.com/?tag=ACME
SSH Username: ec2-user (or 'rocky' depending on the class)
SSH Key Download: See link at the bottom of the Server IP Dashboard.

Note: If the dashboard does not load immediately, please wait a minute for DynamoDB to sync.
=================================================================
```

---

### `sync-dynamo`

Push instance IP / team metadata to the DynamoDB table, used by the dashboard. (rarely needed, as this sync is normally done during instance creation)

#### Syntax

```bash
./bin/learnomancer sync-dynamo <slug> <region>
```

#### Help

```bash
Usage:
learnomancer sync_dynamo [-h] <slug> <region>

Options:

--help, -h  Display this help.

Arguments:

slug    The slug/suffix for the class
region  The region to list from
```

#### Example

```bash
$ ./bin/learnomancer sync-dynamo ACME us-west-2
- Found 18 instances to sync
-- Added db1 to team 1
-- Added db2 to team 1
...
```

---

### `vpc-config`

Rebuild the local class config file from an existing VPC (rarely needed).

#### Syntax

```bash
./bin/learnomancer vpc-config <slug> <region>
```

#### Help

```bash
Usage:
learnomancer vpc_config [-h] <slug> <region>

Options:

--help, -h  Display this help.

Arguments:

slug    The slug/suffix for the class
region  The region to rebuild the config for
```

#### Example

```bash
$ ./bin/learnomancer vpc-config ACME us-west-2
Config rebuilt for 'ACME' in 'us-west-2' with VPC 'Percona-Training-ACME-VPC' (vpc-0abc123def4567890)
```

---

## Developing / Contributing

### Project layout

| Path                         | Purpose                                           |
| ---------------------------- | ------------------------------------------------- |
| `bin/learnomancer`           | CLI entry point (`CommandRunner` + `Application`) |
| `src/Application.php`        | Registers console commands                        |
| `src/AwsClient.php`          | EC2 / VPC AWS SDK wrapper                         |
| `src/DynamoDbClient.php`     | DynamoDB AWS SDK wrapper                          |
| `src/LearnomancerConfig.php` | Local `.config-*.cnf` persistence                 |
| `src/Command/`               | One CakePHP command class per sub-command         |
| `src/Command/Helper/`        | Console output helpers                            |
| `config/learnomancer.php`    | Application configuration                         |
| `hosts.yml`, `roles/`        | Ansible provisioning                              |
| `packer/`                    | AMI build templates                               |

### CakePHP Console

This project is a **CakePHP Console application**, not a full CakePHP web app. The sub-commands extend `Cake\Console\BaseCommand`, define arguments/options in `buildOptionParser()`, and implement `execute()`. New sub-commands should:

1. Add a dedicated class under `src/Command/`
2. Register it in `src/Application.php` (`$commands->add('my-command', ...)`)
3. Keep AWS logic in `AwsClient.php`, or `DynamoDbClient.php` rather than in the command when possible

### PHPStan

Static analysis runs at **level 10** on `src/` (see `phpstan.neon`). Helpers under `src/Command/Helper/` are excluded, as they are 3rd party.

```bash
composer stan
```

Prefer fixing real types (narrow nullable CLI args, accurate `@return` array shapes) over ignores or casts.

### PHPCS

Code style is enforced with PHP_CodeSniffer using PSR-12 / CakePHP ruleset customizations (`phpcs.xml`).

```bash
composer cs-check   # report violations
composer cs-fix     # auto-fix where possible
```

### Other Composer scripts

```bash
composer lint       # PHP parallel-lint (syntax)
```

### Pull Requests

1. Fork and branch from `main`
2. Implement the change with matching style
3. Run `composer stan` and `composer cs-check` locally
4. Open a PR against `main`

See [CONTRIBUTING.md](CONTRIBUTING.md) for bug reports, enhancements, and community notes.
