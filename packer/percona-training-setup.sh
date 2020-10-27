#!/bin/bash	

set -e  # Exit build if any command below fails/exits

# 
# percona-training-setup.sh
#
# A Packer shell provisioner script that creates an AMI
# for use within Percona Training classrooms for basic
# MySQL operations.
# 
# This script performs the following operations:
# - Installs a previously uploaded my.cnf
# - Installs Percona YUM repo
# - Installs latest Percona Server 8.0
# - Extracts an xtrabackup image containing imdb, sakila, and world schemas
# - Starts MySQL and displays last 50 lines of error log
# 

# Check for uploaded my.cnf
if [ ! -f /tmp/my.cnf ]; then
	echo "!! MISSING my.cnf !! Aborting !!"
	exit 1
fi

echo "### Installing Percona-Toolkit Conf"
mkdir /etc/percona-toolkit
mv /tmp/percona-toolkit.conf /etc/percona-toolkit/

echo "### Install Percona Repo"
yum install -y http://repo.percona.com/yum/percona-release-latest.noarch.rpm
percona-release setup ps80

echo "### Install Latest Percona Server 8.0"
yum install -y \
	percona-server-server.x86_64 \
	percona-server-client.x86_64 \
	percona-mysql-shell.x86_64 \
	percona-server-rocksdb.x86_64 \
	percona-server-shared.x86_64 \
	percona-server-shared-compat.x86_64 \
	percona-xtrabackup-80.x86_64 \
	percona-toolkit.x86_64 \
	qpress

# Download/install xtrabackup of IMDB/world/sakila
echo "### Downloading backup from S3..."
mkdir -p /var/lib/mysql
curl https://s3.amazonaws.com/percona-training/imdb_world_sakila_20200320.xbstream | xbstream -C /var/lib/mysql -xv

echo "### Decompressing .qp files..."
xtrabackup --decompress --remove-original --parallel 4 --compress-threads 4 --target-dir /var/lib/mysql/

echo "### Preparing backup..."
xtrabackup --prepare --target-dir /var/lib/mysql/

echo "### Fix permissions..."
chown -R mysql:mysql /var/lib/mysql

echo "### Installing /etc/my.cnf"
mv /tmp/my.cnf /etc/my.cnf

echo "### Install SSL Certs"
mkdir /etc/ssl/mysql
mv /tmp/*.pem /etc/ssl/mysql/
chown mysql:mysql /etc/ssl/mysql/*.pem
chmod 644 /etc/ssl/mysql/*.pem
chmod 600 /etc/ssl/mysql/{ca-key.pem,server-key.pem}

# For sysbench 1.0; hardcoded
ln -s /etc/ssl/mysql/ca.pem /etc/ssl/mysql/cacert.pem

echo "### Starting MySQL..."
systemctl start mysqld.service
sleep 10 && journalctl -n 50 -u mysqld --no-pager

echo "### Remove Backup Files"
mysql -uroot -BNe "SELECT COUNT(*) FROM world.city" >/dev/null
if [ $? -eq 0 ]; then
  rm -rf /var/lib/mysql/imdb_world_sakila_20200320.xbstream /var/lib/mysql/xtrabackup_*
fi

echo "### Create root/imdb/sysbench user"
cat <<- EOF | mysql -uroot

	CREATE DATABASE IF NOT EXISTS sysbench;

	DROP USER IF EXISTS 'sbuser'@'localhost';
	CREATE USER 'sbuser'@'localhost' IDENTIFIED WITH mysql_native_password BY 'sbPass1234#';
	GRANT ALL ON sysbench.* TO 'sbuser'@'localhost';

	DROP USER IF EXISTS 'sbuser'@'10.%';
	CREATE USER 'sbuser'@'10.%' IDENTIFIED WITH mysql_native_password BY 'sbPass1234#';
	GRANT ALL ON sysbench.* TO 'sbuser'@'10.%';

	DROP USER IF EXISTS 'imdb'@'localhost';
	CREATE USER 'imdb'@'localhost' IDENTIFIED WITH mysql_native_password BY 'imDb1234#';
	GRANT ALL ON imdb.* TO 'imdb'@'localhost';

	DROP USER IF EXISTS 'imdb'@'10.%';
	CREATE USER 'imdb'@'10.%' IDENTIFIED WITH mysql_native_password BY 'imDb1234#';
	GRANT ALL ON imdb.* TO 'imdb'@'10.%';

	CREATE USER IF NOT EXISTS 'root'@'127.0.0.1' IDENTIFIED WITH mysql_native_password BY 'Perc0na1234#';
	GRANT ALL PRIVILEGES ON *.* TO 'root'@'127.0.0.1';

	ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY 'Perc0na1234#';

	DELETE FROM mysql.user WHERE user = '';
	DELETE FROM mysql.user WHERE authentication_string = '';

	FLUSH PRIVILEGES; FLUSH LOGS; RESET MASTER;
EOF

# mysql -uroot -e "CREATE FUNCTION fnv1a_64 RETURNS INTEGER SONAME 'libfnv1a_udf.so'"
# mysql -uroot -e "CREATE FUNCTION fnv_64 RETURNS INTEGER SONAME 'libfnv_udf.so'"
# mysql -uroot -e "CREATE FUNCTION murmur_hash RETURNS INTEGER SONAME 'libmurmur_udf.so'"

echo "### MySQL shutdown"
systemctl stop mysqld
rm -f /var/lib/mysql/auto.cnf /var/lib/mysql/backup-my.cnf /var/lib/mysql/slow.log \
      /var/lib/mysql/binlog.* /var/lib/mysql/mysqld-bin.* /var/lib/mysql/*.pem
rm -f /var/log/mysqld.log

echo "### Install sysbench Scripts"
mv /tmp/{prepare_sysbench.sh,run_imdb_workload.sh,run_sysbench_oltp.sh} /usr/local/bin/
mv /tmp/imdb_workload.lua /home/centos/
chmod 755 /usr/local/bin/{prepare_sysbench.sh,run_imdb_workload.sh,run_sysbench_oltp.sh}

echo "### Install myq_status"
curl -L https://github.com/jayjanssen/myq-tools/releases/download/1.0.4/myq_tools.tgz >/tmp/myq_tools.tgz
tar -C /tmp/ -xvzf /tmp/myq_tools.tgz
rm -f /tmp/bin/{myq_status.darwin-386,myq_status.darwin-amd64,myq_status.freebsd-386,myq_status.freebsd-amd64,myq_status.freebsd-arm,myq_status.linux-386,myq_status.linux-arm}
mv /tmp/bin/myq_status.linux-amd64 /usr/local/bin/myq_status

#----------------------------------------------
echo "### Finished percona-training-setup.sh provisioning"
sync && sleep 10 && sync
