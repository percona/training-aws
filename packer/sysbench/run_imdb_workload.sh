#!/bin/bash

sysbench /home/centos/imdb_workload.lua \
  --db-driver=mysql \
  --db-ps-mode=disable \
  --skip_trx=on \
  --mysql-user=imdb \
  --mysql-password=imDb1234# \
  --mysql-db=imdb \
  --mysql-host=mysql1 \
  --mysql-ignore-errors=all \
  --report-interval=1 \
  --threads=4 \
  --time=0 \
  --events=0 \
  run | grep tps
