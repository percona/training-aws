packer {
	required_plugins {
		docker = {
			source	= "github.com/hashicorp/docker"
			version = "~> 1"
		}
	}
}

locals {
	container_repo = "percona-training"
	container_tag = "${formatdate("YYYYMMDD", timestamp())}"
}

source "docker" "rocky" {
	image = "rockylinux:9-minimal"
	commit = true
}

build {

	name = local.container_tag
	sources = ["source.docker.rocky"]

	provisioner "file" {
		destination = "/tmp/my.cnf"
		source		= "my.cnf.txt"
	}

	provisioner "file" {
		destination = "/tmp"
		source		= "etc_ssl_mysql/"
	}

	provisioner "file" {
		destination = "/tmp"
		source		= "sysbench/"
	}

	provisioner "shell" {
		script = "docker-base.sh"
		execute_command = "chmod +x {{ .Path }}; {{ .Vars }} sh '{{ .Path }}'"
	}

	post-processors {
		post-processor "docker-tag" {
			repository = local.container_repo
			tags = [local.container_tag]
		}
	}

# 	post-processors {
# 		post-processor "docker-tag" {
# 			repository = "public.ecr.aws/YOUR REGISTRY ALIAS HERE/YOUR REGISTRY NAME HERE"
# 			tags	   = ["latest"]
# 		}
# 
# 		post-processor "docker-push" {
# 			"ecr_login": true,
# 			"aws_access_key": "YOUR KEY HERE",
# 			"aws_secret_key": "YOUR SECRET KEY HERE",
# 			login_server = "public.ecr.aws/YOUR REGISTRY ALIAS HERE"
# 		}
# 	}

}
