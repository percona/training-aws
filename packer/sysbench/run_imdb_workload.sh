#!/bin/bash

sysbench /home/centos/imdb_workload.lua \
  --mysql-user=imdb \
  --mysql-password=imDb1234# \
  --mysql-db=imdb \
  --time=0 --threads=4 --report-interval=1 --events=0 \
  run
