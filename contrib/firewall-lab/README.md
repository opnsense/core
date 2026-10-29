# Firewall Lab

This folder contains a small, defensive lab kit for studying an OPNsense
firewall in an isolated environment you own or are explicitly allowed to test.

## Suggested topology

- OPNsense
  - WAN: NAT or bridged network for updates only
  - LAN: internal network, for example `192.168.56.1/24`
- Study client
  - Linux/Kali/Ubuntu/Parrot
  - LAN address example: `192.168.56.10`
- Test server
  - Linux host or VM
  - LAN address example: `192.168.56.20`

Keep the WebGUI and SSH reachable from LAN only while studying. Avoid exposing
management services on WAN.

## Start a simple target service

On the test server, run:

```sh
docker compose -f contrib/firewall-lab/docker-compose.yml up -d lab-http
```

This starts an HTTP service on port `8080`. Use it to validate allow/block rules,
NAT, aliases, and log visibility.

An intentionally vulnerable training app is included as an optional profile:

```sh
docker compose -f contrib/firewall-lab/docker-compose.yml --profile training up -d juice-shop
```

Use that only on an isolated lab network.

## Run firewall checks

From the study client, run:

```sh
contrib/firewall-lab/firewall_lab_check.sh --i-own-this-lab 192.168.56.1
```

The script only accepts private or local target addresses and performs basic
connectivity/service checks. It does not exploit services.

## First exercises

1. Confirm the OPNsense LAN IP answers ping from the study client.
2. Check which management ports are visible from LAN.
3. Block the study client from reaching the test server on TCP/8080.
4. Confirm the connection fails from the client.
5. Open `Firewall > Log Files > Live View` and verify the block entry.
6. Move the block rule below a broad allow rule and observe the behavior.
7. Restore the correct rule order and re-test.
8. Run `System > Firmware > Status > Run an Audit` in OPNsense.

## Notes

- Prefer testing one rule at a time.
- Save a configuration backup before larger changes.
- Record the expected result before running each test.
- If a test surprises you, check rule order, interface direction, NAT, and logs.
