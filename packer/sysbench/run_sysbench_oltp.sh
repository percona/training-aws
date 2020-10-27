#!/bin/bash

cd /etc/mysql/ssl/

sysbench \
	/usr/share/sysbench/oltp_read_write.lua \
	--db-driver=mysql \
	--db-ps-mode=disable \
	--skip_trx=on \
	--mysql-user=sbuser \
	--mysql-password=sbPass1234# \
	--mysql-db=sysbench \
	--mysql-host=mysql1 \
	--mysql-ignore-errors=all \
	--mysql-ssl=on \
	--tables=1 \
	--table_size=1000000 \
	--report-interval=1 \
	--threads=1 \
	--time=0 \
	--events=0 \
	--rate=10 \
	run | grep tps
