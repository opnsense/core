#!/bin/sh

set -eu

usage() {
    echo "Usage: $0 --i-own-this-lab <private-or-local-target-ip>"
    exit 64
}

is_allowed_target() {
    case "$1" in
        10.*|192.168.*|172.1[6-9].*|172.2[0-9].*|172.3[0-1].*|127.*|169.254.*)
            return 0
            ;;
        *)
            return 1
            ;;
    esac
}

check_tcp() {
    port="$1"
    label="$2"

    if nc -vz -w 2 "$target" "$port" >/tmp/firewall_lab_nc.$$ 2>&1; then
        printf "OPEN   tcp/%-5s %s\n" "$port" "$label"
    else
        printf "closed tcp/%-5s %s\n" "$port" "$label"
    fi
    rm -f /tmp/firewall_lab_nc.$$
}

if [ "$#" -ne 2 ] || [ "$1" != "--i-own-this-lab" ]; then
    usage
fi

target="$2"

if ! is_allowed_target "$target"; then
    echo "Refusing to test non-private/non-local target: $target" >&2
    exit 65
fi

if ! command -v nc >/dev/null 2>&1; then
    echo "Missing required command: nc" >&2
    exit 69
fi

echo "Target: $target"
echo

if command -v ping >/dev/null 2>&1; then
    if ping -c 1 -W 2 "$target" >/dev/null 2>&1; then
        echo "ICMP   reachable"
    else
        echo "ICMP   no reply"
    fi
else
    echo "ICMP   skipped; ping not found"
fi

echo
check_tcp 22 "SSH"
check_tcp 53 "DNS TCP"
check_tcp 80 "HTTP"
check_tcp 443 "HTTPS/WebGUI"
check_tcp 853 "DNS over TLS"
check_tcp 1194 "OpenVPN"
check_tcp 3000 "training app"
check_tcp 51820 "WireGuard"
check_tcp 8080 "lab HTTP"
check_tcp 8443 "alternate HTTPS"

echo
if command -v curl >/dev/null 2>&1; then
    echo "HTTP headers, if available:"
    curl -k -I --connect-timeout 2 --max-time 5 "https://$target/" 2>/dev/null | sed 's/^/  /' || true
    curl -I --connect-timeout 2 --max-time 5 "http://$target/" 2>/dev/null | sed 's/^/  /' || true
else
    echo "HTTP header checks skipped; curl not found"
fi

echo
echo "Next: compare these results with Firewall > Log Files > Live View."
