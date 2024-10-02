packer {
  required_plugins {
    amazon = {
      source  = "github.com/hashicorp/amazon"
      version = "~> 1"
    }
  }
}

locals {
  image_name = "Percona-Training-MongoDB-${formatdate("YYYYMMDD", timestamp())}"
}

source "amazon-ebs" "percona-training-mongo" {
  ami_block_device_mappings {
    delete_on_termination = true
    device_name           = "/dev/sda1"
    volume_size           = 100
    volume_type           = "gp2"
  }
  ami_name                    = "${local.image_name}-AMI"
  ami_regions                 = ["us-west-2"]
  associate_public_ip_address = "true"
  instance_type               = "t2.large"
  launch_block_device_mappings {
    delete_on_termination = true
    device_name           = "/dev/sda1"
    volume_size           = 100
    volume_type           = "gp2"
  }
  region       = "us-west-2"
  source_ami   = "ami-0a248ce88bcc7bd23"
  ssh_pty      = "true"
  ssh_username = "centos"
  subnet_id    = "subnet-094292b1d75a3b882"
  tags = {
    Name = "${local.image_name}-AMI"
  }
  vpc_id = "vpc-0a4028f1c54b4bcf4"
}

build {
  sources = ["source.amazon-ebs.percona-training-mongo"]

  provisioner "shell" {
    execute_command = "chmod +x {{ .Path }}; {{ .Vars }} sudo -E sh '{{ .Path }}'"
    script          = "ami-base.sh"
  }

  provisioner "shell" {
    execute_command = "chmod +x {{ .Path }}; {{ .Vars }} sudo -E sh '{{ .Path }}'"
    script          = "percona-training-setup-mongo.sh"
  }

}
