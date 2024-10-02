packer {
  required_plugins {
    amazon = {
      source  = "github.com/hashicorp/amazon"
      version = "~> 1"
    }
  }
}

locals {
  image_name = "Percona-Training-${formatdate("YYYYMMDD", timestamp())}"
}

source "amazon-ebs" "percona-training" {

  ami_block_device_mappings {
    delete_on_termination = true
    device_name           = "/dev/sda1"
    volume_size           = 100
    volume_type           = "gp2"
  }
  ami_name                    = local.image_name
  ami_regions                 = ["us-west-1", "us-west-2", "us-east-1", "eu-west-1", "eu-central-1"]
  associate_public_ip_address = "true"
  instance_type               = "t3.large"
  launch_block_device_mappings {
    delete_on_termination = true
    device_name           = "/dev/sda1"
    volume_size           = 100
    volume_type           = "gp2"
  }
  region = "us-west-2"
  run_tags = {
    Name = local.image_name
  }
  run_volume_tags = {
    Name = "Percona-Training-Builder-EBS"
  }
  source_ami   = "ami-0a248ce88bcc7bd23"
  ssh_pty      = "true"
  ssh_username = "centos"
  subnet_id    = "subnet-0d627f49a25875a1a"
  vpc_id       = "vpc-0b7e5bd4e7eef4806"
}

build {
  sources = ["source.amazon-ebs.percona-training"]

  provisioner "file" {
    destination = "/tmp/my.cnf"
    source      = "my.cnf.txt"
  }

  provisioner "file" {
    destination = "/tmp"
    source      = "etc_ssl_mysql/"
  }

  provisioner "file" {
    destination = "/tmp"
    source      = "sysbench/"
  }

  provisioner "shell" {
    execute_command = "chmod +x {{ .Path }}; {{ .Vars }} sudo -E sh '{{ .Path }}'"
    script          = "ami-base.sh"
  }

  provisioner "shell" {
    execute_command = "chmod +x {{ .Path }}; {{ .Vars }} sudo -E sh '{{ .Path }}'"
    script          = "percona-training-setup-mysql.sh"
  }

}
