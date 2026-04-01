#!/bin/sh

TYPE="$1"
SERVER_KEY="$2"
if [ "$TYPE" = "tls-crypt-v2-client" ]; then
    [ -z "$SERVER_KEY" ] && exit 1
    TMP=$(mktemp)
    trap 'rm -f "$TMP"' EXIT
    printf '%s' "$SERVER_KEY" | base64 -d > "$TMP"
    # OpenVPN writes both the generated key and validation errors to stdout.
    # After generating the client key, OpenVPN attempts to re-open the output
    # file for validation. When using /dev/stdout this fails, and error messages
    # are appended to stdout. Therefore we extract only the PEM block between
    # BEGIN and END markers.
    /usr/local/sbin/openvpn \
        --tls-crypt-v2 "$TMP" \
        --genkey tls-crypt-v2-client \
        /dev/stdout | awk '
        /-----BEGIN/ { printing=1 }
        printing { print }
        /-----END/ { exit }
    ' | base64
else
    /usr/local/sbin/openvpn --genkey "$TYPE" /dev/stdout | awk '
        /-----BEGIN/ { printing=1 }
        printing { print }
        /-----END/ { exit }
    ' | base64
fi
