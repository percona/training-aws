# Percona Training AWS Scripts

## AMI List

* Percona-Training-20200710-AMI

Every AWS zone needs a different AMI. Here's a list of AMI numbers for each zone:

| Zone          | Region         | AMI                   |
| --------------|----------------|-----------------------|
| N. Virginia   | us-east-1      | ami-07ebd9e603e9b63ca |
| N. California | us-west-1      | ami-0559f840e19a9dd87 |
| Oregon        | us-west-2      | ami-0b02004363780dd42 |
| Frankfurt     | eu-central-1   | ami-098d6253feafb8037 |
| Ireland       | eu-west-1      | ami-0cc06bddb2f744955 |

* Always verify you are using the LATEST AMI:
  * Leave off `-i` and the script will show you all available AMIs in this region.
  * Pick the AMI that has the most recent datetime

* Percona-Training-MongoDB-20210107-AMI

The MongoDB AMI is available in us-west-2 only at this time. The ID is ami-0c2adaf7cfbf731ec

```
$ ./start-instances.php -a ADD -r eu-west-1 -p TREK -c 6 -m db1
You must set the AMI to use for the training instances.
The following Percona-Training AMIs were found in the 'eu-west-1' region:

Name                           - AMI
Percona-Training-20200321-AMI  - ami-0e8223fee4b885841
Percona-Training-20200710-AMI  - ami-0b02004363780dd42
...

```

## Installation

### PHP

You need PHP 5.6+ on your laptop to run the scripts.

### AWS Credentials

Make sure you have your `~/.aws/credentials` configured:

```
$ cat ~/.aws/credentials 
[default]
aws_access_key_id = ...
aws_secret_access_key = ...
```

### Ansible

Install ansible on your laptop.

On Mac, easy with Homebrew:

```
$ brew install ansible php@7.4
```

## Set Up Notes

NOTE: 'TREK' is used below as an example. Use a "short code" that represents your client during training. Examples: 'DELL' if you were training Dell Co.

### Machine Types

There are multiple "machine types" which are used in different training courses:
  * db1: Used for the 'Scaling and Optimization' course. Exercises in the various chapters can be executed on db1. The 'MyMovies' chapter is a team-building exercise. You would assign 2-3 students for each db1 instance. This instance is also used for 'Operations and Troubleshooting' when doing xtrabackup labs, and functions as the master all master/slave exercises.
  * db2: This machine type is used as the slave instance for all all master/slave exercises.
  * scoreboard: This is for the MyMovies competition. Ansible will handle 100% of the configuration. You simply need to open the page in your browser (port 8080) and display on projector/monitor for students to see.
  * app: This instance serves as sysbench, docker, and proxysql for the XtraDB Cluster Tutorial. Each student should get 1 app instance.
  * mysql1, mysql2, mysql3: These instances are used in the XtraDB Cluster Tutorial. Each student should get 1 of each of these.
  * node1: This instance is used in the PXC K8S Operator tutorial. Each student should receive 1 of these.*
  * mongodb: This instance has the Percona Server for MongoDB packages. Each student should receive 1 of these for the MongoDB training.

## Set Up Instances

### 1. Create a new VPC

All instances need to run inside a VPC. The VPC will launch with a single subnet of 10.11.0.0/16 with outbound internet capabilities.

```
./setup-vpc.php -a ADD -r eu-west-1 -p TREK
```

This will create a VPC in the `eu-west-1` region named 'Percona-Training-TREK'. It will create all necessary security group rules for allowing SSH (22), HTTP (80), HTTP-SSL (443), and HTTP-ALT (8080).

### 2. Add/Start EC2 Instances

Using the same suffix, TREK, we can launch instances inside the above VPC.

```
./start-instances.php -a ADD -r eu-west-1 -p TREK -c 6 -m db1 -i ami-9f10fbec
```

The above example will launch 6 instances of the db1 image in the VPC. They will be named 'Percona-Training-TREK-db1-T[1-6]'.

If you need to launch other instance types, simply repeat the above command and change the `-m` parameter.

### 2a. Launch multiple instance types

You can launch multiple instance types at the same time. Separate each type with `,`

```
./start-instances.php -a ADD -r eu-west-1 -p TREK -c 4 -m app,mysql1,mysql2,mysql3 -i ami-014230ad6c3e10ec2
```

The above example will launch 4 complete setups for use in the PXC tutorial. A total of 16 EC2 instances would be created.

### 2b. Add More Instances

If you need to add more instances (ie: more teams, or more students) you can do so using the -o (offset) to make sure the numbers match up. -c is the number of instances to add.

```
./start-instances.php -a ADD -r eu-west-1 -p TREK -c 1 -o 6 -m db1 -i ami-9f10fbec
```

In this example, the offset `-o` is 6. The next numbered instance will start at 7. The above command will launch 1 `-c 1` more instance named 'Percona-Training-TREK-db1-T7'

### 3. Get `ansible_hosts` and configure hosts

Once all machines are up and running, we can generate an `ansible_hosts` file, which we can use to provision the servers.

```
./start-instances.php -a GETANSIBLEHOSTS -r eu-west-1 -p TREK > ansible_hosts_trek
```

There is only 1 ansible playbook: hosts.yml. This playbook contains tasks for all of the different machine types by adding/removing yum repos, configuring the */etc/hosts* file, installing necessary software packages, checking out git repos, and much more.

You can re-run this playbook as needed. That's the nice thing about ansible; it only changes what needs to be changed to set a specific state.

Ideally, though, you would create all of your machine types then run this playbook only once.

```
# ansible-playbook -i ansible_hosts_trek hosts.yml
```

If you add additional servers and need to provision them, you need to repeat the `GETANSIBLEHOSTS` command, and also repeat the `ansible-playbook` command. But you can specify a single server to make ansible go faster:

```
# ansible-playbook -i ansible_hosts_trek hosts.yml --limit mysql1-T7    // Provision the mysql1-T7 server only

# ansible-playbook -i ansible_hosts_trek hosts.yml --limit T7    // Provision all servers from Team 7
```

### 5. Teams and Connecting to Instances

Load the following URL to your presentation screen, and/or share the URL within chat, substituting XXXX for your "prefix":

http://percona-training.s3-website-us-east-1.amazonaws.com/?tag=XXXX

This will display all servers created for each team, along with their public and private IP addresses.

Have the students download the keys .zip file from the URL at the bottom of this page. Mac/Linux users must `chmod 600 Percona-Training.key`

Next, assign teams. Just point at each student and say "You are team 1, you are team 2, etc".
_Exception:_ For the MyMovies competition, assign 2 students per 1 team.

Once the keys and teams are distributed, students can connect to instances.

The SSH username is 'centos'. There is **NO password**. Windows/Putty users can use the PPK file.

### 6. Removing Instances

After the training is done, you need to remove the instances and the VPC

```
./start-instances.php -a DROP -r eu-west-1 -i ami-9f80fbec -p TREK
```

Unfortunately, you need to manually remove the VPC in the AWS web-console interface:

- Go to https://aws.amazon.com
- Go to the region where you created the instances
- Click `VPC`
- Go to `Your VPCs`
- Select the VPC (in the example named `Percona-Training-TREK`) and click `Delete VPC`. 

This will delete all the VPCs, subnets, gateways...

## Alternate Setup

For the *Scaling and Optimization* class, the students might find it more beneficial to have their own instance on which to run the query tuning exercises. This is a bit more work for you, the instructor, but is easially managed thanks to the scripts above. Here is an example timeline for this setup:

* Day 0: Some time before day 1, you know only 14 students will be attending. Create 15 DB1 instances (one additional for instructor). This will be 14 teams, one student per team. Run ansible as normal.
* Day 1 (Class): Distribute servers as normal. Assign each student to their own team. Do exercises as normal.
* Day 1 (Hotel): Destroy all 15 instances. Create 7 DB1 instances for 7 teams, 2 students per team, and 1 scoreboard instance. Run ansible as normal.
* Day 2 (Class): Re-distribute servers as IPs will change. Assign students, in pairs, to new teams. Run My-Movies exercise.
* Day 2 (Hotel): Destroy everything. If your class is continuing with *Operations and Troubleshooting*, re-launch 15 DB1 and 15 DB2 instances. Run ansible as normal.
* Day 3 (Class): Again, re-distribute servers. Run remainder of class/exercises as normal.
