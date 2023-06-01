# How to generate a new AMI using packer

* Packer is a tool from Hasicorp that makes creating AMI very easy. You just write some bash, make a config and Packer does the rest.
* The `percona-training-ami-NNN.json` packer config file specifies the base AMI (CentOS 9), which regions you want yours in, name, volume sizes, etc.
* Look at the section "provisioners" within the packer config. The provisioners are executed in order. The last two, base.sh and percona-training-setup-NNN.sh do most of the setup.
* Each time we need a new ami, we need to create a new Packer config (percona-training-ami-NNN.json) and a new "percona-training-setup-NNN.sh"
* The idea with Packer is to create a base AMI. For example, with MySQL, the AMI is a single MySQL install with some dummy data. 
* We then launch X EC2 instances of that AMI for a training session. Then, run an ansible script against those instances to do any specific setup depending on the class. If it's a PXC class, then Ansible will setup M/SS replication. If it's a DBA101 class, then ansible will erase everything from each student's 2nd machine. etc. etc.
* It depends on what you want to do in the labs. Do you want them to install mongo from scratch? Then you may not need an AMI at all, and we can just use the base CentOS AMI. Do you want mongo installed but not configured? Maybe an AMI to have mongo and utils installed with dummy data already on disk.

1. Install awscli and configure keys with `aws configure`

2. Prepare create percona-training-ami-NNN.json

3. Prepare create percona-training-setup-NNN.sh

4. Run `packer build percona-training-ami-NNN.json`
