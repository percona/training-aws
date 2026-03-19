#!/bin/bash
# setup-class.sh
# Maps a class short-name (slug) to the appropriate machine types and provisions them.

CLASS_SLUG="$1"
CLIENT="$2"
TEAMS="$3"
REGION="${4:-us-west-2}"

if [ -z "$CLASS_SLUG" ] || [ -z "$CLIENT" ] || [ -z "$TEAMS" ]; then
    echo "Usage: $0 <class-slug> <Client Suffix> <Number of Teams> [Region]"
    echo "Example: $0 mysql-dev TREK 14 eu-west-1"
    echo ""
    echo "Available Class Slugs:"
    echo "  MySQL: mysql-ops, mysql-dev, mysql-101, mysql-oracle-dba, proxysql, mysql-k8s, pxc, gr, gr-101"
    echo "  MongoDB: mongo-ops, mongo-dev"
    echo "  PostgreSQL: pg-ops, pg-dev, pg-tutorial"
    exit 1
fi

MACHINE_TYPES=""

case "$CLASS_SLUG" in
    "mysql-ops")
        MACHINE_TYPES="db1,db2"
        ;;
    "mysql-dev"|"mysql-101"|"mysql-oracle-dba"|"proxysql")
        MACHINE_TYPES="db1"
        ;;
    "mysql-k8s")
        MACHINE_TYPES="node1"
        ;;
    "pxc")
        MACHINE_TYPES="pxc"
        ;;
    "gr"|"gr-101")
        MACHINE_TYPES="gr"
        ;;
    "mongo-ops"|"mongo-dev")
        MACHINE_TYPES="mongodb"
        ;;
    "pg-ops"|"pg-dev"|"pg-tutorial")
        MACHINE_TYPES="db1"
        ;;
    *)
        echo "Error: Unknown class slug: '$CLASS_SLUG'"
        echo "Run without arguments to see the list of valid slugs."
        exit 1
        ;;
esac

echo "Starting setup for '$CLASS_SLUG' (Instances: $MACHINE_TYPES) for client $CLIENT ($TEAMS teams) in $REGION..."

echo "[1/4] Creating VPC..."
./setup-vpc.php -a ADD -r "$REGION" -p "$CLIENT"

echo "[2/4] Launching Instances..."
# For simplicity, we assume the user already knows the AMI or we pick a generic one. But normally we should fetch the latest.
# Let's try to get the first AMI listed from start-instances.php.
LATEST_AMI=$(./start-instances.php -a ADD -r "$REGION" -p dummy -c 1 -m db1 2>&1 | grep "AMI" | grep -v 'Name' | head -n 1 | awk '{print $NF}')

if [[ "$LATEST_AMI" != ami-* ]]; then
    echo "Could not detect the latest AMI automatically. Please update setup-class.sh or pass an AMI manually."
    exit 1
fi

echo "Using AMI: $LATEST_AMI"
./start-instances.php -a ADD -r "$REGION" -p "$CLIENT" -c "$TEAMS" -m "$MACHINE_TYPES" -i "$LATEST_AMI"

echo "[3/4] Generating Ansible hosts file..."
./start-instances.php -a GETANSIBLEHOSTS -r "$REGION" -p "$CLIENT" > "ansible_hosts_$CLIENT"

echo "[4/4] Provisioning with Ansible..."
ansible-playbook -i "ansible_hosts_$CLIENT" hosts.yml

echo "Setup complete! Run 'make summary client=$CLIENT' to get the class handout."
