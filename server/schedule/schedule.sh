#!/bin/bash
step=1 #间隔的秒数

for (( i = 0; i<31536000; i=(i+step) )); do
	 /usr/bin/php /www/foreign_currency/artisan schedule:run
	  sleep $step
  done

  exit 0
