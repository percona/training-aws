#!/bin/bash		

echo "### Starting Package Upgrades"
dnf -y upgrade

echo "### Installing useful packages"
dnf install -y epel-release
dnf install -y nano vim screen git telnet unzip lsof socat wget sysstat htop openssl python3-dnf-plugin-versionlock iptables-services iptables-utils
dnf download httpd php php-mysqlnd psmisc

# Don't require tty for sudoers
sed -i "s/^.*requiretty/#Defaults requiretty/" /etc/sudoers

# Clean up cloud-init
if [ -f /etc/cloud/cloud.cfg ]; then
	echo "### Configure cloud-init"

	# - no ssh pw authentication
	sed -i "s/^ssh_pwauth:   1$/ssh_pwauth:   0/" /etc/cloud/cloud.cfg

	# remove any keys created by packer
	sed -i "s/^ssh_deletekeys:   0$/ssh_deletekeys:   1/" /etc/cloud/cloud.cfg

	# user 'rocky' user
	sed -i "s/name: cloud-user/name: rocky/" /etc/cloud/cloud.cfg
fi

# remove colorized nano
sed -i "s/^include /#include /" /etc/nanorc

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

# Make sure iptables starts on boot
systemctl enable iptables


# Flush changes to disk
sync && sleep 1 && sync
