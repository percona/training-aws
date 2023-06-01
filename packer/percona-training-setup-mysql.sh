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
# - Installs Percona repo
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
mkdir -p /etc/percona-toolkit
cat <<TOOLKIT >/etc/percona-toolkit/percona-toolkit.conf
no-version-check
TOOLKIT

echo "### Install Percona Repo"
dnf install -y http://repo.percona.com/yum/percona-release-latest.noarch.rpm
percona-release setup ps80 -y
percona-release enable pt

echo "### Install Percona Server 8.0.32"
dnf versionlock percona-server-*-8.0.32 percona-xtrabackup-*-8.0.32
dnf install -y \
	percona-server-server.x86_64 \
	percona-server-client.x86_64 \
	percona-mysql-shell.x86_64 \
	percona-server-rocksdb.x86_64 \
	percona-server-shared.x86_64 \
	percona-xtrabackup-80.x86_64 \
	percona-toolkit.x86_64 \
	perl-DBD-MySQL \
	qpress

# Download/install xtrabackup of IMDB/world/sakila
echo "### Downloading backup from S3..."
mkdir -p /var/lib/mysql
curl -sS https://s3.amazonaws.com/percona-training/imdb_world_sakila_20200320.xbstream | xbstream -C /var/lib/mysql -xv

echo "### Decompressing .qp files..."
xtrabackup --decompress --remove-original --parallel 4 --compress-threads 4 --target-dir /var/lib/mysql/

echo "### Preparing backup..."
xtrabackup --prepare --target-dir /var/lib/mysql/

echo "### Fix permissions..."
chown -R mysql:mysql /var/lib/mysql

echo "### Installing /etc/my.cnf"
mv /tmp/my.cnf /etc/my.cnf

echo "### Install SSL Certs"
mkdir -p /etc/ssl/mysql
mv /tmp/*.pem /etc/ssl/mysql/
chown mysql:mysql /etc/ssl/mysql/*.pem
chmod 644 /etc/ssl/mysql/*.pem
chmod 600 /etc/ssl/mysql/ca-key.pem

echo "### Starting MySQL..."
systemctl start mysql
sleep 10 && journalctl -n 10 -u mysqld --no-pager && tail -50 /var/log/mysqld.log

echo "### Remove Backup Files"
mysql -uroot -pPerc0na1234# -BNe "SELECT COUNT(*) FROM world.city" >/dev/null
if [ $? -eq 0 ]; then
  rm -rf /var/lib/mysql/imdb_world_sakila_20200320.xbstream /var/lib/mysql/xtrabackup_*
fi

echo "### Create root/imdb/sysbench user"
cat <<- EOF | mysql -uroot -pPerc0na1234#

	CREATE DATABASE IF NOT EXISTS sysbench;

	DROP USER IF EXISTS 'sbuser'@'localhost';
	CREATE USER 'sbuser'@'localhost' IDENTIFIED BY 'sbPass1234#';
	GRANT ALL ON sysbench.* TO 'sbuser'@'localhost';

	DROP USER IF EXISTS 'sbuser'@'10.%';
	CREATE USER 'sbuser'@'10.%' IDENTIFIED BY 'sbPass1234#';
	GRANT ALL ON sysbench.* TO 'sbuser'@'10.%';

	DROP USER IF EXISTS 'imdb'@'localhost';
	CREATE USER 'imdb'@'localhost' IDENTIFIED BY 'imDb1234#';
	GRANT ALL ON imdb.* TO 'imdb'@'localhost';

	DROP USER IF EXISTS 'imdb'@'10.%';
	CREATE USER 'imdb'@'10.%' IDENTIFIED BY 'imDb1234#';
	GRANT ALL ON imdb.* TO 'imdb'@'10.%';

	CREATE USER IF NOT EXISTS 'root'@'127.0.0.1' IDENTIFIED BY 'Perc0na1234#';
	GRANT ALL PRIVILEGES ON *.* TO 'root'@'127.0.0.1';

	ALTER USER 'root'@'localhost' IDENTIFIED BY 'Perc0na1234#';

	DELETE FROM mysql.user WHERE user = '';
	DELETE FROM mysql.user WHERE authentication_string = '';

	FLUSH PRIVILEGES; FLUSH LOGS; RESET MASTER;
EOF

echo "### MySQL shutdown"
systemctl stop mysql
rm -f /var/lib/mysql/auto.cnf /var/lib/mysql/backup-my.cnf /var/lib/mysql/slow.log \
      /var/lib/mysql/binlog.* /var/lib/mysql/mysqld-bin.* /var/lib/mysql/*.pem
rm -f /var/log/mysqld.log

echo "### Install sysbench 1.1 and scripts"
dnf install -y luajit
wget https://lefred.be/wp-content/uploads/2023/01/sysbench-1.1.0-2.el9.x86_64.rpm
rpm -Uvh sysbench-1.1.0-2.el9.x86_64.rpm --nodeps

mv /tmp/{prepare_sysbench.sh,run_imdb_workload.sh,run_sysbench_oltp.sh} /usr/local/bin/
mv /tmp/imdb_workload.lua /home/ec2-user/
chmod 755 /usr/local/bin/{prepare_sysbench.sh,run_imdb_workload.sh,run_sysbench_oltp.sh}
chown ec2-user /usr/local/bin/{prepare_sysbench.sh,run_imdb_workload.sh,run_sysbench_oltp.sh}

echo "### Install myq_status"
curl -L https://github.com/jayjanssen/myq-tools/releases/download/1.0.4/myq_tools.tgz >/tmp/myq_tools.tgz
tar -C /tmp/ -xvzf /tmp/myq_tools.tgz
rm -f /tmp/bin/{myq_status.darwin-386,myq_status.darwin-amd64,myq_status.freebsd-386,myq_status.freebsd-amd64,myq_status.freebsd-arm,myq_status.linux-386,myq_status.linux-arm}
mv /tmp/bin/myq_status.linux-amd64 /usr/local/bin/myq_status

#----------------------------------------------
echo "### Finished percona-training-setup.sh provisioning"
sync && sleep 10 && sync
