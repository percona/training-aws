#!/bin/bash	

set -e  # Exit build if any command below fails/exits

# 
# percona-training-setup-mongo.sh
#
# A Packer shell provisioner script that creates an AMI
# for use within Percona Training classrooms for basic
# MongoDB operations.
# 
# This script performs the following operations:
# - Installs Percona YUM repo
# - Installs latest Percona Server for MongoDB 4.2
# 

echo "### Install Percona Repo"
dnf install -y http://repo.percona.com/yum/percona-release-latest.noarch.rpm
percona-release setup -y psmdb42

echo "### Install Latest Percona Server for MongoDB 4.2"
dnf install -y \
	percona-server-mongodb.x86_64 

echo "### Creating dbpath..."
mkdir -p /mongodb/data

echo "### Fix permissions..."
chown -R mongod: /mongodb

#----------------------------------------------
echo "### Finished percona-training-setup-mongo.sh provisioning"
sync && sleep 10 && sync
