# Class Setup Guide

This guide documents the full workflow for creating a new training class in AWS with `learnomancer`.

It is written for the current workflow where a MongoDB class may still be provisioned with the `mysql-k8s` class type until a dedicated Mongo class flow is added.

---

## 1. Log In To The Percona AWS Account

Before you create a class, make sure your local AWS CLI is authenticated against the Percona AWS account.

If you use AWS console access:

1. Sign in to the Percona AWS console with your assigned account.
2. Go to IAM.
3. Open your IAM user.
4. Create a new access key.
5. Save both values:
   - `aws_access_key_id`
   - `aws_secret_access_key`

If your team uses a named AWS profile, you can store the keys in `~/.aws/credentials` under that profile name. If you use the default profile, the file should look like this:

```ini
[default]
aws_access_key_id = YOUR_ACCESS_KEY_ID
aws_secret_access_key = YOUR_SECRET_ACCESS_KEY
```

You can verify the credentials with:

```bash
aws sts get-caller-identity
```

If your environment uses a non-default profile, export it before running Learnomancer:

```bash
export AWS_PROFILE=percona-training
```

---

## 2. Clone The Repository

```bash
git clone git@github.com:percona/training-aws.git
cd training-aws/
composer install
```

If you already have the repository, pull the latest changes first:

```bash
git pull
composer install
```

---

## 3. Create The Class

There is not always a dedicated MongoDB class flow available yet, so for MongoDB training labs you can currently use the `mysql-k8s` class template.

This Minikube-based class path is pinned to the latest Percona-tested combination for the MongoDB Operator stack:

- Minikube: `1.38.1`
- Kubernetes inside Minikube: `v1.35.1`

Example:

```bash
./bin/learnomancer create-class mysql-k8s ACME us-west-2 1 ami-0abc123def4567890
```

What this does:

- Creates the VPC
- Launches the EC2 instances for the class
- Generates the class summary
- Generates the SSH config file
- Generates the Ansible inventory file

If you want to see the available class names:

```bash
./bin/learnomancer
```

---

## 4. Generate The Ansible Inventory

After the class is created, generate the Ansible inventory file if you want to provision the hosts with Ansible.

```bash
./bin/learnomancer ansible-config -o ACME us-west-2
```

This writes a file named:

```bash
ansible_ACME
```

Download the `Percona-Training.key` private key from the class dashboard’s SSH key download link, and place it in the `training-aws/` directory before running Ansible or SSH.

Then apply the provisioning playbook:

```bash
chmod 600 Percona-Training.key
ansible-playbook -i ansible_ACME hosts.yml
```

Notes:

- `Percona-Training.key` is the SSH private key used for the training hosts.
- Keep the key file permission set to `600`.

Optional: add the key to your SSH agent.

```bash
ssh-add Percona-Training.key
```

---

## 5. Generate SSH Config

Generate the SSH host entries for the class:

```bash
./bin/learnomancer ssh-config -o ACME us-west-2
```

This writes a file named:

```bash
ssh_ACME
```

That file contains host aliases like:

```sshconfig
Host Percona-Training-ACME-node1-T1 node1-T1
  HostName X.X.X.X
  User rocky
  IdentityFile Percona-Training.key
  StrictHostKeyChecking no
  ForwardAgent yes
```

You can use it in two ways:

### Option A: Use it directly

```bash
ssh -F ssh_ACME node1-T1
```

### Option B: Add it to `~/.ssh/config`

The simplest approach is to include the generated file from your SSH config:

```sshconfig
Include /absolute/path/to/training-aws/ssh_ACME
```

After that, you can connect with:

```bash
ssh node1-T1
```

If you prefer, you can also copy the generated `Host` entries directly into `~/.ssh/config` instead of using `Include`.

---

## 6. Find The Connection Details

The class summary command prints the dashboard URL and SSH reminders:

```bash
./bin/learnomancer summary ACME
```

That output is the quickest way to confirm:

- the dashboard URL
- the SSH username
- the key file name

For example, the SSH username is typically `rocky` for these training hosts.

---

## 7. Install PMM Server For The Class

Install PMM Server on the machine that will host the class monitoring stack. This lets students connect their PMM clients to a shared PMM Server instead of installing their own.

Run the PMM easy-install script as `root` or with `sudo`:

```bash
curl -fsSL https://www.percona.com/get/pmm | bash
```

What this does:

- Installs Docker if needed
- Creates the `pmm-net` Docker network
- Creates the `pmm-data` volume
- Starts the `pmm-server` container

After setup completes, log into PMM at one of the listed URLs using:

- username: `admin`
- password: `admin`

The script usually prints several possible URLs, including the host’s external IP and `https://127.0.0.1:443/`.
Use the machine’s reachable IP address for students.

If your browser warns about the default certificate, that is expected with the bundled self-signed cert.

### Create A Service Account And Token

In PMM, create a service account token for the class:

1. Log in to PMM.
2. Open `Users and access > Service accounts`.
3. Click `Add service account`.
4. Give it a short, unique name.
5. Choose the `Admin` role.
6. Click `Create`.
7. Click `Add service account token`.
8. Give the token a name, or leave it blank.
9. Click `Generate token`.
10. Copy the token immediately. It usually starts with `glsa_`.

Keep this token private and share it only with students who need to register PMM clients for the class.

### How Students Connect Their PMM Clients

Students will use the PMM Server IP or DNS name you share with them, plus the service account token.

Example registration command:

```bash
pmm-admin config \
  --server-insecure-tls \
  --server-url=https://service_token:YOUR_GLSA_TOKEN@PMM_SERVER_IP:443 \
  <NODE_ADDRESS> <NODE_TYPE> <NODE_NAME>
```

Example:

```bash
pmm-admin config \
  --server-insecure-tls \
  --server-url=https://service_token:glsa_xxxxxxxxxxxxxxxxxxxxx@10.11.102.4:443 \
  node1 generic node1-T1
```

Notes:

- `service_token` is the username literal, not a placeholder
- `YOUR_GLSA_TOKEN` is the token you copied from PMM
- Replace `PMM_SERVER_IP` with the IP address students can reach from their machines
- Remove `--server-insecure-tls` only if you install trusted certificates on PMM Server

---

## 8. Deploy MinIO For PBM Backups

This repository includes a reusable MinIO manifest that the `minikube` Ansible role copies to `/root/minio.yaml`, applies, and waits on during `ansible-playbook`. It creates:

- the `mongodb-tutorial` namespace
- MinIO root credentials
- the PBM backup credentials secret
- persistent storage for MinIO
- the MinIO server deployment and service
- a bucket-creation job for the `pbm` bucket

The manifest pins MinIO server and client images from `quay.io` by digest. This
avoids Docker Hub `latest` image changes and makes every classroom deployment
use the same verified images. On each playbook run, Ansible removes and
recreates the bucket-creation Job; this is necessary because Kubernetes Jobs
cannot have their pod template changed in place, and it lets a rerun recover
automatically from a previous failed image pull.

When you configure the Percona Server for MongoDB backup storage, use the MinIO service name inside the cluster:

```yaml
backup:
  enabled: true
  storages:
    minio:
      type: s3
      s3:
        bucket: pbm
        region: us-east-1
        credentialsSecret: my-cluster-name-backup-minio
        endpointUrl: http://minio.mongodb-tutorial.svc.cluster.local:9000/
        forcePathStyle: true
        prefix: T1
```

---

## 9. Generate MongoDB Traffic for PMM QAN

[`scripts/mongodb-qan-workload.js`](scripts/mongodb-qan-workload.js) is a
dependency-free `mongosh` workload generator. It seeds an `orders` collection,
then continually runs randomized `find`, `insert`, and `update` operations.
It creates both indexed and deliberately non-indexed query shapes so they are
easy to compare in PMM Query Analytics.

Run it from any host with `mongosh` and network access to the MongoDB service.
Keep credentials in the connection URI environment variable, not in the script:

```bash
export MONGODB_URI='mongodb://QAN_USER:QAN_PASSWORD@MONGODB_HOST:27017/?authSource=admin'
QAN_DATABASE=qan_demo QAN_OPERATIONS_PER_SECOND=20 QAN_DURATION_SECONDS=0 \
  mongosh "$MONGODB_URI" --file scripts/mongodb-qan-workload.js
```

Set `QAN_DURATION_SECONDS` to a positive duration for a finite run, or press
`Ctrl-C` to stop an infinite run. Set `QAN_RESET=true` to drop and recreate the
demo collection before starting.

The operator configuration uses `slowOpThresholdMs: 200`. Most demo operations
will be faster than that on a class-sized environment, so temporarily lower it
(for example, to `1`) in the Custom Resource when demonstrating QAN. Apply the
updated Custom Resource, wait for the cluster to reconcile, and restore the
normal threshold after the lab.

---

## 10. Full Example Workflow

```bash
git clone git@github.com:percona/training-aws.git
cd training-aws/
composer install

# Log in to AWS first and make sure ~/.aws/credentials is populated

./bin/learnomancer create-class mysql-k8s ACME us-west-2 1 ami-0abc123def4567890
./bin/learnomancer ansible-config -o ACME us-west-2
chmod 600 Percona-Training.key
ansible-playbook -i ansible_ACME hosts.yml
./bin/learnomancer ssh-config -o ACME us-west-2
```

Then add the generated SSH config file to `~/.ssh/config` and connect with:

```bash
ssh node1-T1
```

---

## 11. Troubleshooting Notes

- If `aws sts get-caller-identity` fails, fix your AWS credentials before creating the class.
- If `ansible-playbook` cannot connect, confirm `Percona-Training.key` exists and is mode `600`.
- If `ssh node1-T1` does not resolve, make sure `ssh_ACME` is included in `~/.ssh/config` or use `ssh -F ssh_ACME node1-T1`.
- If you later add a dedicated MongoDB class type, replace `mysql-k8s` in the create-class example with the Mongo-specific class.
