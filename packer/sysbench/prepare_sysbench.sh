#!/bin/bash

sysbench \
	/usr/share/sysbench/oltp_read_write.lua \
	--db-driver=mysql \
	--auto_inc=off \
	--tables=1 \
	--table_size=1000000 \
	--threads=2 \
	--report-interval=1 \
	--mysql-host=mysql1 \
	--mysql-user=sbuser \
	--mysql-password=sbPass1234# \
	--mysql-db=sysbench \
	--mysql-ssl=verify_ca \
	--mysql-ssl-ca=/etc/ssl/mysql/ca.pem \
	prepare
