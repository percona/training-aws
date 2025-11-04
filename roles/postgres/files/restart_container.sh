#!/bin/bash

# Check for required argument
if [[ $# -ne 1 ]]; then
  echo "Usage: $0 <node_number>"
  echo "Where <node_number> must be 1, 2, or 3"
  exit 1
fi

NODE_NUM="$1"

# Validate that it's 1, 2, or 3
if [[ "$NODE_NUM" != "1" && "$NODE_NUM" != "2" && "$NODE_NUM" != "3" ]]; then
  echo "Error: Invalid node number. Must be 1, 2, or 3."
  exit 2
fi

# Compute dynamic values
PORT=$((5410 + NODE_NUM))
VOLUME="pg${NODE_NUM}-pgdata"
HOSTNAME="pgc${NODE_NUM}"
CONTAINER_NAME="pgc${NODE_NUM}"

# Start Docker container
docker start ${CONTAINER_NAME}

docker exec -u 0 -d ${CONTAINER_NAME} /sbin/sshd