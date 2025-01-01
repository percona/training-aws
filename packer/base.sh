#!/bin/bash		

osversion=`grep -o '[0-9]\+\.[0-9]\+' /etc/rocky-release | cut -d. -f1`

echo "### Starting Package Upgrades"
dnf -y upgrade

echo "### Installing useful packages"
dnf install -y https://dl.fedoraproject.org/pub/epel/epel-release-latest-$osversion.noarch.rpm
/usr/bin/crb enable

dnf install -y nano vim screen git telnet unzip lsof socat wget sysstat htop sudo cloud-init openssl
dnf clean all

# Don't require tty for sudoers
sed -i "s/^.*requiretty/#Defaults requiretty/" /etc/sudoers

# Clean up cloud-init
if [ -f /etc/cloud/cloud.cfg ]; then
	echo "### Configure cloud-init"

	# - no ssh pw authentication
	sed -i "s/^ssh_pwauth:   1$/ssh_pwauth:   0/" /etc/cloud/cloud.cfg

	# remove any keys created by packer
	sed -i "s/^ssh_deletekeys:   0$/ssh_deletekeys:   1/" /etc/cloud/cloud.cfg
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

# Flush changes to disk
sync && sleep 1 && sync
