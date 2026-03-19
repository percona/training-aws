#!/bin/bash
# setup-class.sh
# Maps a class name to the appropriate machine types and provisions them.

CLASS_NAME="$1"
CLIENT="$2"
TEAMS="$3"
REGION="${4:-us-west-2}"

if [ -z "$CLASS_NAME" ] || [ -z "$CLIENT" ] || [ -z "$TEAMS" ]; then
    echo "Usage: $0 \"<Class Name>\" <Client Suffix> <Number of Teams> [Region]"
    echo "Example: $0 \"MySQL Training for Developers\" TREK 14 eu-west-1"
    exit 1
fi

MACHINE_TYPES=""

case "$CLASS_NAME" in
    "MySQL Training for Database Operations Specialists")
        MACHINE_TYPES="db1,db2"
        ;;
    "MySQL Training for Developers"|"DBA Hands-On (MySQL 101)"|"MySQL for Oracle DBA's")
        MACHINE_TYPES="db1"
        ;;
    "ProxySQL Tutorial")
        MACHINE_TYPES="db1"
        ;;
    "Percona Operator for MySQL based on Percona XtraDB Cluster")
        MACHINE_TYPES="node1"
        ;;
    "Percona XtraDB Cluster Tutorial")
        MACHINE_TYPES="pxc"
        ;;
    "MySQL Group Replication 101"|"Percona Group Replication Tutorial")
        MACHINE_TYPES="gr"
        ;;
    "MongoDB Training for Database Operations Specialists"|"MongoDB Training for Developers")
        MACHINE_TYPES="mongodb"
        ;;
    "PostgreSQL Training for Database Operations Specialists"|"PostgreSQL Training for Developers"|"PostgreSQL Tutorial")
        MACHINE_TYPES="db1"
        ;;
    *)
        echo "Error: Unknown class name: $CLASS_NAME"
        exit 1
        ;;
esac

echo "Starting setup for '$CLASS_NAME' (Instances: $MACHINE_TYPES) for client $CLIENT ($TEAMS teams) in $REGION..."

echo "[1/4] Creating VPC..."
./setup-vpc.php -a ADD -r "$REGION" -p "$CLIENT"

echo "[2/4] Launching Instances..."
# For simplicity, we assume the user already knows the AMI or we pick a generic one. But normally we should fetch the latest.
# This part might fail if no AMI is provided and the PHP script doesn't default correctly. The php script requires -i or it stops.
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
