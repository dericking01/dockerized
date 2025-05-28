#!/bin/bash
set -e

# Create log dir if not exists
mkdir -p /var/log/kannel

# Determine service type based on command
if [[ "$1" == "bearerbox" ]]; then
    exec bearerbox /etc/kannel/kannel.conf
elif [[ "$1" == "smsbox" ]]; then
    # Wait for bearerbox to be ready
    until nc -z bearerbox 6009; do
        echo "Waiting for bearerbox..."
        sleep 2
    done
    exec smsbox /etc/kannel/kannel.conf
else
    exec "$@"
fi