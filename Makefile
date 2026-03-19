.PHONY: setup teardown summary

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
