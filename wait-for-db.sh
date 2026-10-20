#!/bin/bash

if [ -z "$1" ]
then
  echo "Database is necessary"
  exit 1
fi

n=0;
max=10
while [ -z "$(docker ps -q -f health=healthy -f name=anydataset_db_$1)" ] && [ "$n" -lt "$max" ];
do
  echo "Waiting for $1...";
  n=$(( n + 1 ))
  sleep 5;
done

if [ -z "$(docker ps -q -f health=healthy -f name=anydataset_db_$1)" ]
then
  echo "$1 was not healthy after $(( max * 5 ))s"
  exit 2
fi

echo "$1 is up"
