{% raw %}
#!/bin/bash
# Script to display the IP addresses of containers in the pgnet Docker network
docker network inspect pgnet --format='{{range .Containers}}{{.Name}} : {{.IPv4Address}}{{"\n"}}{{end}}'
{% endraw %}