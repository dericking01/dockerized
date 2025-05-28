#!/bin/bash

desc_array=(serviceA serviceB ServiceC) 
ip_array=(197.250.9.149 197.250.9.191 41.217.203.61)
port_array=(6202 23000 30010)

counter=0

for fruit in "${ip_array[@]}"; do
HOST=${ip_array[$counter]}
PORT=${port_array[$counter]}
SERVICE=${desc_array[$counter]}
counter=$(($counter + 1))
if echo "quit" | telnet $HOST $PORT 2>/dev/null | grep -q "Escape character is"; then
   echo "Telnet connection to $HOST:$PORT Service $SERVICE is  successful"
else
response=$(curl -X POST -d "key=Uncxy7VvNcUjdfjLLdkfjdsdssxcfXX&service=$SERVICE&port=$PORT&iphost=$HOST" http://192.168.1.49/callfile/network.php)
#echo "Telnet connection failed"
fi
done
