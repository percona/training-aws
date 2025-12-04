# Percona Training AWS Scripts

## AMI List

```
ami-0c2adaf7cfbf731ec - Centos 7 - MongoDB 4.4 (old)
ami-0b02004363780dd42 - Centos 7 - MySQL (old)
ami-0f5dcffa34c281c1a - Rocky 9 - MySQL / Kubernetes Operators
ami-0ad8bfd4b10994785 - Centos 9 - MySQL / MongoDB 7.0
```

The MongoDB AMI is available only in `us-west-2` region at this time. 

* If the latest AMI doesn't work, try the previous AMI
  * Leave off `-i` and the script will show you all available AMIs in this region:
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

### Packages required

You need PHP 7.2+ on your laptop to run the scripts. Also AWS CLI and Ansible are required.

On Mac, easy with Homebrew:

```
$ brew install php@7.2 ansible awscli composer
```

On Linux the following packages are required:

```
$ sudo apt -y install php7.2 php-xml php-mbstring ansible awscli composer php-curl
```

After you install composer, install all necessary PHP packages:

```
$ composer install
```

This will create a `vendor/` directory, with all the 3rd party libraries needed.

### AWS Credentials

Make sure you have your `~/.aws/credentials` configured:

```
$ cat ~/.aws/credentials 
[default]
aws_access_key_id = ...
aws_secret_access_key = ...
```

## Set Up Notes

NOTE: `TREK` is used below as an example. Use a "short code" that represents your client during training. Examples: `DELL` if you were training Dell Co.

### Machine Types

There are multiple "machine types" which are used in different training courses:
  * `db1`: Used for the 'Scaling and Optimization' course. Exercises in the various chapters can be executed on db1. The 'MyMovies' chapter is a team-building exercise. You would assign 2-3 students for each db1 instance. This instance is also used for 'Operations and Troubleshooting' when doing xtrabackup labs, and functions as the master all master/slave exercises.
  * `db2`: This machine type is used as the slave instance for all all master/slave exercises.
  * `scoreboard`: This is for the MyMovies competition. Ansible will handle 100% of the configuration. You simply need to open the page in your browser (port 8080) and display on projector/monitor for students to see.
  * `app`: This instance serves as sysbench, docker, and proxysql for the XtraDB Cluster and Group Replication tutorials. Each student should get 1 app instance.
  * `mysql1`, `mysql2`, `mysql3`: These instances are used in the XtraDB Cluster and Group Replication tutorials. Each student should get 1 of each of these.
  * `node1`: This instance is used in the K8S Operator tutorials. Each student should receive 1 of these.
  * `mongodb`: This instance has the Percona Server for MongoDB packages. Each student should receive 1 of these for the MongoDB training.

There are 2 machine type aliases, `gr` and `pxc`, both are aliases for all 4 types: `app`, `mysql1`, `mysql2`, and `mysql3`

## Set Up Instances

### 1. Ensure DynamoDB table exists

Make sure there is a DynamoDB table created on the `us-east-1` region called `percona_training_servers`. This is used to support the training backed but sometimes is deleted. If it is not there, create it with the following structure:

* `Partition Key`: `teamTag` (String)
* `Sort Key`: `teamID` (Number)

### 2. Create a new VPC

All instances need to run inside a VPC. The VPC will launch with a single subnet of 10.11.0.0/16 with outbound internet capabilities.

```
./setup-vpc.php -a ADD -r eu-west-1 -p TREK
```

This will create a VPC in the `eu-west-1` region named `Percona-Training-TREK`. It will create all necessary security group rules for allowing SSH (22), HTTP (80), HTTP-SSL (443), and HTTP-ALT (8080).

### 3. Add/Start EC2 Instances

Using the same suffix (TREK in this case) we can launch instances inside the above VPC:

```
./start-instances.php -a ADD -r eu-west-1 -p TREK -c 6 -m db1 -i ami-9f10fbec
```

The above example will launch 6 instances of the db1 image in the VPC. They will be named `Percona-Training-TREK-db1-T[1-6]`.

If you need to launch other instance types, simply repeat the above command and change the `-m` parameter.

### 3a. Launch multiple instance types

You can launch multiple instance types at the same time. Separate each type with `,` or use the two aliases.

```
-- Launch 4 complete setups for use in the PXC tutorial. A total of 16 (4 teams, each with 4 servers) EC2 instances will be created.
./start-instances.php -a ADD -r eu-west-1 -p TREK -c 4 -m pxc -i ami-014230ad6c3e10ec2

-- Launch 7 setups for Operations class, db1 and db2. This will launch 14 instances total.
./start-instances.php -a ADD -r us-west-2 -p TREK -c 7 -m db1,db2 -i ami-014230ad6c3e10ec2
```

### 3b. Add More Instances

If you need to add more instances (i.e.: more teams, or more students) you can do so using the `-o` (offset) to make sure the numbers match up. `-c ` is the number of instances to add.

```
./start-instances.php -a ADD -r eu-west-1 -p TREK -c 1 -o 6 -m db1 -i ami-9f10fbec
```

In this example, the offset `-o` is 6. The next numbered instance will start at 7. The above command will launch 1 `-c 1` more instance named `Percona-Training-TREK-db1-T7`

### 4. Generate `ansible_hosts` and configure hosts

Once all machines are up and running, we can generate an `ansible_hosts` file, which we can use to provision the servers.

```
./start-instances.php -a GETANSIBLEHOSTS -r eu-west-1 -p TREK > ansible_hosts_trek
```

There is only 1 ansible playbook: `hosts.yml`. This playbook contains tasks for all of the different machine types by adding/removing yum repos, configuring the */etc/hosts* file, installing necessary software packages, checking out git repos, and much more.

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

The SSH username is usually `ec2-user` but depending on the AMI it can be different (e.g. `centos`). There is **NO password**. Windows/Putty users can use the PPK file.

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

## Tutorials/Exercises

Pages are live-generated. Source is under the `gh-pages` branch of https://github.com/percona/training-material

* Source/Replica Tutorial (Exercises for MySQL Operations & Troubleshooting)
* ProxySQL Tutorial
* Mesosphere Tutorial
* Percona XtraDB Cluster Tutorial
* Orchestrator Tutorial
* PostgreSQL Tutorial
* PMM Tutorial

## MyMovies Exercise

* Launch 1 db1 instance for each team, and 1 [scoreboard](https://github.com/percona/training-mymovies/blob/master/scoreboard/README.md) that is shared.
* Scoreboard will hit 3 different pages on all dbX instances 
* Teams compete to who can get the app to perform better
* Check app files under /var/www/html on each instance for the app's PHP code
* The `mymovies` app runs on port 80 of each `db1` instance (httpd)
* The `scoreboard` is a Nodejs app listening on port 8080 of the `scoreboard` server
* [Solutions](https://github.com/percona/training-material/blob/master/slides/modules/my_movies/my_movies_solutions.md)

## Survey

At the end of the training, share a survey with the participants

1. Create a PDF of your slides. everything in 1 PDF.
2. Upload PDF to Google Drive
3. Create share “if have the link” of the PDF
4. Clone the [survey](https://docs.google.com/forms/d/12GCDBdzwGrOaM-MA3lxJziPUtkL37mDGWVvn41aMlDw/edit), click Settings, Presentation, edit Confirmation message. Paste in URL of PDF.

