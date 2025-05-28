#!/bin/bash
desc_array=(serviceA serviceB ServiceC)
ip_array=(197.250.9.149 197.250.9.191 41.217.203.61)
port_array=(6202 23000 30010)
current_datetime=$(date +"%Y-%m-%d %H:%M:%S")
filename=$(date +%Y%m%d)

counter=0

for fruit in "${ip_array[@]}"; do
HOST=${ip_array[$counter]}
PORT=${port_array[$counter]}
SERVICE=${desc_array[$counter]}
counter=$(($counter + 1))
if echo "quit" | telnet $HOST $PORT 2>/dev/null | grep -q "Escape character is"; then
   echo "$current_datetime :: Telnet connection to $HOST:$PORT Service $SERVICE is  successful">>/var/log/telnet$filename.log
else
echo "$current_datetime :: Telnet connection to $HOST:$PORT Service $SERVICE is  failed">>/var/log/telnet$filename.log
fi
done
