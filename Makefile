.PHONY: help setup teardown summary list-amis

help:
	@echo "================================================================="
	@echo "              Percona Training AWS Infrastructure                "
	@echo "================================================================="
	@echo "Usage: make <target> [arguments]"
	@echo ""
	@echo "Targets:"
	@echo "  setup         Deploy a training environment"
	@echo "                Args: class=<slug> client=<suffix> teams=<num> [region=<aws-region>]"
	@echo "                Example: make setup class=mysql-dev client=TREK teams=14 region=eu-west-1"
	@echo ""
	@echo "  teardown      Destroy a training environment"
	@echo "                Args: client=<suffix> [region=<aws-region>]"
	@echo "                Example: make teardown client=TREK region=eu-west-1"
	@echo ""
	@echo "  summary       Show the dashboard URL and connection info"
	@echo "                Args: client=<suffix>"
	@echo "                Example: make summary client=TREK"
	@echo ""
	@echo "  list-amis     List available Percona Training AMIs"
	@echo "                Args: [region=<aws-region>]"
	@echo "                Example: make list-amis region=eu-west-1"
	@echo ""
	@echo "  help          Show this help message"
	@echo "================================================================="
	@echo ""
	@echo "Rich Examples:"
	@echo "  # Deploy a 10-team MongoDB class in US-East-1:"
	@echo "  make setup class=mongodb-admin client=NYC teams=10 region=us-east-1"
	@echo ""
	@echo "  # Clean up the TREK environment in the default region (us-west-2):"
	@echo "  make teardown client=TREK"
	@echo ""
	@echo "  # Quickly check which AMIs are available in London:"
	@echo "  make list-amis region=eu-west-2"
	@echo "================================================================="

# Example: make list-amis region=eu-west-1
list-amis:
	@./start-instances.php -a LISTAMIS -r "$${region:-us-west-2}"

# Example: make setup class=mysql-dev client=TREK teams=14 region=eu-west-1
setup:
	@if [ -z "$(class)" ] || [ -z "$(client)" ] || [ -z "$(teams)" ]; then \
		echo "Usage: make setup class=<class-slug> client=<Suffix> teams=<Number> [region=<AWS Region>]"; \
		echo "Run './setup-class.sh' without arguments to see the list of valid slugs."; \
		exit 1; \
	fi
	@bash ./setup-class.sh "$(class)" "$(client)" "$(teams)" "$(region)"

# Example: make teardown client=TREK region=eu-west-1
teardown:
	@if [ -z "$(client)" ]; then \
		echo "Usage: make teardown client=<Suffix> [region=<AWS Region>]"; \
		exit 1; \
	fi
	./start-instances.php -a DROP -r "$${region:-us-west-2}" -p "$(client)" -i dummy
	./setup-vpc.php -a DROP -r "$${region:-us-west-2}" -p "$(client)"

# Example: make summary client=TREK
summary:
	@if [ -z "$(client)" ]; then \
		echo "Usage: make summary client=<Suffix>"; \
		exit 1; \
	fi
	@echo "================================================================="
	@echo "                 TRAINING ENVIRONMENT READY                      "
	@echo "================================================================="
	@echo "Server IP Dashboard: http://percona-training.s3-website-us-east-1.amazonaws.com/?tag=$(client)"
	@echo "SSH Username: ec2-user (or 'rocky' depending on the class)"
	@echo "SSH Key Download: See link at the bottom of the Server IP Dashboard."
	@echo ""
	@echo "Note: If the dashboard doesn't load immediately, please wait a minute for DynamoDB to sync."
	@echo "================================================================="
