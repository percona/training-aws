{% raw %}
#!/bin/bash
# Script to display the IP addresses of containers in the pgnet Docker network
hosts_to_add=$(docker network inspect pgnet --format='{{range .Containers}}{{.IPv4Address}} {{.Name}}{{"\n"}}{{end}}' | sed 's/\/16//')
echo "${hosts_to_add}" | sudo tee -a /etc/hosts
{% endraw %}