#!/bin/bash	

# 
# Percona Live - Austin 2019
# XtraDB Cluster Tutorial
# 

# Check for uploaded my.cnf
if [ ! -f /tmp/my.cnf ]; then
	echo "!! MISSING my.cnf !! Aborting !!"
	exit 1
fi

echo "### Installing Percona-Toolkit Conf"
mkdir /etc/percona-toolkit
mv /tmp/percona-toolkit.conf /etc/percona-toolkit/

echo "### Installing /etc/my.cnf.d/training_base.cnf"
mkdir -p /etc/my.cnf.d
mv /tmp/my.cnf /etc/my.cnf.d/training_base.cnf

echo "### Install Percona Repo"
yum install -y http://repo.percona.com/yum/percona-release-latest.noarch.rpm

echo "### ProxySQL 2.0"
yum install --downloadonly proxysql2

echo "### Install Latest Percona XtraDB Cluster 5.7"
yum install -y Percona-XtraDB-Cluster-57 Percona-Server-shared-compat-57 percona-toolkit percona-xtrabackup-24 qpress nc

echo "### Starting MySQL..."
systemctl start mysql
sleep 10

echo "### MySQL shutdown"
systemctl stop mysqld
rm -f /var/lib/mysql/{auto.cnf,backup-my.cnf,mysqld-bin.*,*.pem,slow.log}
rm -f /var/log/mysqld.log

#----------------------------------------------
echo "### Finished provisioning"
sync && sleep 10 && sync
