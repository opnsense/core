# Kernel backup cleanup

The companion `opnsense/update` change supplies transaction callbacks and the
`lockf -k` lock at `/var/run/opnsense-update.lock`. Core supplies the policy,
ownership receipt, normal-startup confirmation, API and firmware settings UI.
Both changes are required for cleanup to become available.

Automatic cleanup is disabled by default. When enabled, it runs hourly after
the configured continuous uptime since normal startup (24 hours by default,
1–720 hours allowed). Manual cleanup requires confirmation of the current
backup generation and bypasses only the elapsed-time/opt-in policy.

Only `/boot/kernel.old` and `/usr/lib/debug/boot/kernel.old` recorded by the
updater can be removed. The receipt binds each backup to directory/file inode
identities, types and SHA-256 content. Additional kernel directories and
standalone `kernel*` ELF images in `/boot` are inventoried and preserved.
Kernel images elsewhere and ZFS snapshots/other boot environments are outside
the deletion scope. Displayed bytes are logical file sizes, not guaranteed
reclaimed space; snapshots can retain the blocks.

Deletion requires a completed update with no deferred kernel/base/package
stages, a subsequent boot of the recorded installed kernel, matching runtime
build information, completion of the normal boot/start-hook sequence, and the
standard kernel selected in declarative loader configuration. It also checks
that the installed updater still implements the lock protocol. On ZFS, the
active boot environment must have the `NR` flags. Custom loader commands,
includes, nextboot settings, links/mounts in backup trees, and loader/module
references to the backup require manual review and block cleanup.

Firmware launcher and updater locks are acquired non-blockingly. Receipt
updates use atomic replacement and fsync. An update sentinel and failure latch
keep cleanup disabled after interruptions or failures. A new successful kernel
installation starts a new ownership generation; cache-only operations cannot
clear an existing failure latch. A replaced updater is accepted at transaction
completion only if it implements protocol 1. Existing backups without an
ownership receipt remain untouched. A partial deletion error leaves the
running kernel and all additional kernels intact; a changed partial backup
requires manual review instead of guessing which files are still owned.

Startup completion and elapsed uptime do not prove that every configured VPN,
driver or other service is healthy. Administrators choose whether to give up
the previous-kernel boot option.

## Isolated tests

From the core repository root:

```sh
python3 -B -m unittest discover -s tests/firmware -p 'test_*.py'
```

The filesystem fixtures are disposable temporary directories. FreeBSD command
responses are simulated in the policy tests. POSIX tests additionally exercise
real `flock` contention. The `lockf` interoperability test runs only on FreeBSD.
These tests do not install firmware or reboot the host.

## Required native validation before requesting merge

- [ ] Run the isolated suite on FreeBSD, including `lockf` interoperability.
- [ ] Run the repository's `make lint` and `make style` checks.
- [ ] Install both patches on a disposable OPNsense VM using the normal package
      workflow; check default-off behavior and the firmware settings UI.
- [ ] On UFS, perform a real kernel update, confirm that cleanup is refused
      before reboot, then test manual cleanup and delayed automatic cleanup.
- [ ] On ZFS, repeat with an `NR` boot environment, and confirm refusal for a
      temporary environment or a different environment selected for reboot.
- [ ] Perform a real major upgrade through the K/B/P stages, including replacement
      of Core and the updater; check that cleanup waits for all stages and reboot.
- [ ] Interrupt/fail an update and verify that the backup remains protected.
- [ ] Boot `kernel.old` and a custom kernel; confirm refusal to clean.
- [ ] Keep at least three extra kernel directories/images; verify their hashes
      and ZFS snapshots are unchanged after cleanup.
- [ ] Start CLI/GUI updates while cleanup is requested and verify exclusion.
- [ ] Check POST/CSRF/ACL behavior and stale confirmation in the real web UI.
- [ ] Measure backup logical size and actual reclaimed space on UFS and ZFS.

The native update/reboot checks and real disk measurements have not been run
in the Windows/Ubuntu WSL development environment.
