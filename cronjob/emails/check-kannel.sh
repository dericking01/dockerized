#!/bin/bash

echo "Checking Kannel services..."
for service in bearerbox smsbox; do
    if pidof $service > /dev/null; then
        echo "$service is running ✅"
    else
        echo "$service is NOT running ❌"
    fi
done

