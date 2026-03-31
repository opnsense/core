#!/bin/sh
echo "$1" | base64 -d > /tmp/tls-crypt-v2.key
/usr/local/sbin/openvpn --tls-crypt-v2 /tmp/tls-crypt-v2.key --genkey tls-crypt-v2-client | base64
