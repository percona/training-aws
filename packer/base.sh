#!/bin/bash		

centosversion=`rpm -qi centos-release  | grep Version | awk '{ print $3}'`

echo "### Starting Package Upgrades"
yum -y upgrade

echo "### Installing useful packages"
yum install -y https://dl.fedoraproject.org/pub/epel/epel-release-latest-$centosversion.noarch.rpm
yum install -y nano vim screen git telnet unzip lsof socat wget sysstat htop sudo cloud-init libselinux-python yum-plugin-downloadonly openssl
yum clean all
yum install --downloadonly httpd php php-mysql mysql-utilities openssl sysbench psmisc

# Don't require tty for sudoers
sed -i "s/^.*requiretty/#Defaults requiretty/" /etc/sudoers

# This is for centos7 which uses cloud-init
if [ -f /etc/cloud/cloud.cfg ]; then
	echo "### Configure cloud-init"

	# - no ssh pw authentication
	sed -i "s/^ssh_pwauth:   1$/ssh_pwauth:   0/" /etc/cloud/cloud.cfg

	# remove any keys created by packer
	sed -i "s/^ssh_deletekeys:   0$/ssh_deletekeys:   1/" /etc/cloud/cloud.cfg
fi

# remove root authorized keys after finish
rm -f /root/.ssh/authorized_keys

# Disable SELinux
echo "### SELinux Permissive"
sed -i 's/^SELINUX=.*/SELINUX=permissive/g' /etc/selinux/config
setenforce Permissive

# Add usr-local-bin path for everyone
cat <<MYPATH >/etc/profile.d/usr-local-bin.sh
#!/bin/bash
export PATH=$PATH:/usr/local/bin
MYPATH

echo "### Remove mariadb-libs"
yum remove -y mariadb-libs

# Flush changes to disk
sync && sleep 1 && sync
