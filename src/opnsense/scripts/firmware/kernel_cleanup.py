#!/usr/local/bin/python3
# SPDX-License-Identifier: BSD-2-Clause

"""Remove an updater-owned kernel backup after a verified subsequent boot."""

import argparse
from contextlib import contextmanager
import hashlib
import json
import os
from pathlib import Path
import re
import shlex
import shutil
import stat
import subprocess
import sys
import tempfile
import time
import uuid
import xml.etree.ElementTree as ET


class UnsafeState(Exception):
    """A prerequisite for deleting a kernel could not be established."""


class KernelCleanup:
    def __init__(self, root=Path('/')):
        self.root = root
        self.version = root / 'usr/local/opnsense/version'
        self.receipt = self.version / 'kernel-cleanup.json'
        self.boot_receipt = root / 'var/run/kernel-cleanup.json'
        self.backups = ('boot/kernel.old', 'usr/lib/debug/boot/kernel.old')

    def command(self, *args):
        return subprocess.check_output(args, text=True, stderr=subprocess.DEVNULL).strip()

    def boot_id(self):
        return self.command('/sbin/sysctl', '-n', 'kern.boottime')

    def root_id(self):
        mount = self.root_mount()
        return [mount[0], mount[2], self.root.stat().st_ino]

    def root_mount(self):
        mounts = self.command('/sbin/mount', '-p').splitlines()
        roots = [line.split() for line in mounts if len(line.split()) > 2 and line.split()[1] == '/']
        if len(roots) != 1:
            raise UnsafeState('Cannot identify the root filesystem')
        return roots[0]

    def read(self, path):
        if path.is_symlink():
            raise UnsafeState('State file is a symbolic link')
        with path.open() as stream:
            value = json.load(stream)
        if not isinstance(value, dict) or value.get('schema') != 1:
            raise UnsafeState('Unsupported kernel cleanup state')
        return value

    def write(self, path, value):
        value['schema'] = 1
        descriptor, name = tempfile.mkstemp(prefix=path.name + '.', dir=path.parent)
        try:
            with os.fdopen(descriptor, 'w') as stream:
                json.dump(value, stream, sort_keys=True)
                stream.flush()
                os.fsync(stream.fileno())
            os.replace(name, path)
            if os.name == 'posix':
                directory = os.open(path.parent, os.O_RDONLY | os.O_DIRECTORY)
                try:
                    os.fsync(directory)
                finally:
                    os.close(directory)
        finally:
            if os.path.exists(name):
                os.unlink(name)

    def signature(self, relative):
        """Bind ownership to this directory, its contents and their identities."""
        path = self.root / relative
        if not path.exists() and not path.is_symlink():
            return None
        if path.resolve() != path or not path.is_dir():
            raise UnsafeState('Kernel directory has a symbolic link or unexpected type')
        device = path.stat().st_dev
        digest = hashlib.sha256()
        size = 0
        for entry in [path] + sorted(path.rglob('*')):
            info = entry.lstat()
            regular_type = stat.S_ISDIR(info.st_mode) or stat.S_ISREG(info.st_mode)
            if info.st_dev != device or entry.is_mount() or not regular_type:
                raise UnsafeState('Kernel directory contains a link, mount or special file')
            digest.update(json.dumps([
                str(entry.relative_to(path)), info.st_ino, info.st_mode
            ]).encode())
            if stat.S_ISREG(info.st_mode):
                with entry.open('rb') as stream:
                    for block in iter(lambda: stream.read(1024 * 1024), b''):
                        digest.update(block)
                size += info.st_size
        return {'digest': digest.hexdigest(), 'bytes': size}

    def kernel_signature(self):
        path = self.root / 'boot/kernel/kernel'
        if path.resolve() != path or not path.is_file():
            raise UnsafeState('Installed kernel has an unexpected path')
        return hashlib.sha256(path.read_bytes()).hexdigest()

    def updater_signature(self):
        content = (self.root / 'usr/local/sbin/opnsense-update').read_bytes()
        if b'\n# kernel-cleanup-protocol: 1\n' not in content:
            raise UnsafeState('The installed updater does not support the kernel cleanup lock protocol')
        return hashlib.sha256(content).hexdigest()

    def pending(self):
        cache = self.root / 'var/cache/opnsense-update'
        return any((cache / ('.' + part + '.pending')).exists() for part in ('kernel', 'base', 'pkgs'))

    def transaction(self):
        token = os.environ.get('OPNSENSE_KERNEL_TRANSACTION', '')
        if not re.fullmatch(r'[0-9a-fA-F-]{36}', token):
            raise UnsafeState('Kernel transaction is not owned by the updater')
        return token

    def begin(self):
        (self.version / 'kernel-cleanup.pending').touch()
        previous = self.read(self.receipt) if self.receipt.exists() else {}
        previous.update({
            'transaction': self.transaction(), 'complete': False,
            'failed': previous.get('failed', False) or previous.get('in_progress', False),
            'in_progress': True
        })
        self.write(self.receipt, previous)

    def record(self):
        # Called only after extraction and kldxref succeeded, under the update lock.
        previous = self.read(self.receipt) if self.receipt.exists() else {'transaction': self.transaction()}
        if previous.get('transaction') != self.transaction():
            raise UnsafeState('Kernel transaction changed during installation')
        self.write(self.receipt, {
            'transaction': self.transaction(), 'generation': str(uuid.uuid4()),
            'complete': False, 'failed': False, 'in_progress': True,
            'root': self.root_id(), 'installed_boot': self.boot_id(),
            'kernel': self.kernel_signature(), 'updater': self.updater_signature(),
            'backups': {name: self.signature(name) for name in self.backups}
        })

    def finish(self, success):
        previous = self.read(self.receipt)
        if previous.get('transaction') != self.transaction():
            raise UnsafeState('Kernel transaction changed before completion')
        previous['failed'] = previous.get('failed', False) or not success
        previous['in_progress'] = False
        previous['complete'] = success and not previous['failed'] and not self.pending()
        if previous['complete'] and 'kernel' in previous:
            # Package updates can replace the updater after the kernel stage.
            previous['updater'] = self.updater_signature()
        self.write(self.receipt, previous)
        if previous['complete']:
            (self.version / 'kernel-cleanup.pending').unlink(missing_ok=True)

    def requires_backup(self, value):
        if 'kernel.old' in value:
            return True
        for name in value.split(';'):
            if name.startswith('/'):
                target = (self.root / name.lstrip('/')).resolve()
                if any(target.is_relative_to(self.root / backup) for backup in self.backups):
                    return True
        return False

    def loader_default(self):
        """Accept the standard declarative loader layout; reject ambiguous overrides."""
        settings = {}
        names = ['boot/defaults/loader.conf', 'boot/device.hints', 'boot/loader.conf']
        confdir = self.root / 'boot/loader.conf.d'
        if confdir.is_symlink():
            raise UnsafeState('Loader configuration directory is a symbolic link')
        if confdir.exists():
            names += [str(path.relative_to(self.root)) for path in sorted(confdir.glob('*.conf'))]
        names += ['boot/loader.conf.local']
        expected = {
            'loader_conf_files': '/boot/device.hints /boot/loader.conf',
            'loader_conf_dirs': '/boot/loader.conf.d',
            'local_loader_conf_files': '/boot/loader.conf.local',
            'nextboot_conf': '/boot/nextboot.conf'
        }
        relevant = set(expected) | {'kernel', 'bootfile', 'nextboot_enable', 'module_path'}
        for name in names:
            path = self.root / name
            if path.resolve() != path:
                raise UnsafeState('Loader configuration is a symbolic link')
            if not path.exists():
                if name == 'boot/defaults/loader.conf':
                    raise UnsafeState('Loader defaults are unavailable')
                continue
            for line in path.read_text().splitlines():
                tokens = shlex.split(line, comments=True)
                if not tokens:
                    continue
                assignment = re.fullmatch(r'([A-Za-z_][A-Za-z_0-9.]*)=(.*)', tokens[0])
                if not assignment or len(tokens) != 1:
                    raise UnsafeState('Loader configuration requires manual review')
                key, value = assignment.groups()
                if self.requires_backup(value):
                    raise UnsafeState('Loader configuration references the backup kernel')
                if name == 'boot/defaults/loader.conf' and key == 'exec' and value == 'echo Loading /boot/defaults/loader.conf':
                    continue
                if value and (key.endswith('_before') or key.endswith('_after') or key == 'exec'):
                    raise UnsafeState('Loader command hooks require manual review')
                if key in relevant:
                    settings[key] = value
        if settings.get('kernel') != 'kernel' or settings.get('bootfile') != 'kernel':
            raise UnsafeState('A different kernel is selected for the next boot')
        for key, value in expected.items():
            if settings.get(key, value) != value:
                raise UnsafeState('Custom loader configuration requires manual review')
        if any(value in settings.get('module_path', '') for value in ('kernel.old', '$', '`')):
            raise UnsafeState('A custom module search path requires manual review')
        if os.path.lexists(self.root / 'boot/nextboot.conf') or settings.get('nextboot_enable', 'NO') != 'NO':
            raise UnsafeState('A nextboot configuration is present')
        for name in ('boot/loader.rc.local', 'boot/loader.lua.local'):
            if os.path.lexists(self.root / name):
                raise UnsafeState('Custom loader code requires manual review')
        # A custom module search path can still require modules in kernel.old.
        module_path = self.command('/sbin/sysctl', '-n', 'kern.module_path')
        if self.requires_backup(module_path):
            raise UnsafeState('The module search path uses the backup kernel')

    def boot_environment(self):
        root_mount = self.root_mount()
        if root_mount[2] == 'zfs':
            environments = [line.split('\t') for line in self.command('/sbin/bectl', 'list', '-H').splitlines()]
            active = [row for row in environments if len(row) >= 2 and 'N' in row[1]]
            if len(active) != 1 or active[0][1] != 'NR':
                raise UnsafeState('The active boot environment is not the persistent default')
        elif root_mount[2] != 'ufs':
            raise UnsafeState('Unsupported root filesystem')

    def verify(self, require_boot=True):
        try:
            receipt = self.read(self.receipt)
        except FileNotFoundError:
            raise UnsafeState('A kernel installation recorded by a compatible updater is required')
        update_ok = receipt.get('complete') is True and receipt.get('in_progress') is False and receipt.get('failed') is False
        if not update_ok or self.pending() or (self.version / 'kernel-cleanup.pending').exists():
            raise UnsafeState('An update is incomplete, failed or pending')
        if receipt.get('root') != self.root_id() or receipt.get('installed_boot') == self.boot_id():
            raise UnsafeState('A subsequent boot of this installation is required')
        bootfile = self.command('/sbin/sysctl', '-n', 'kern.bootfile')
        if bootfile != '/boot/kernel/kernel':
            raise UnsafeState('The standard installed kernel is not running')
        if receipt.get('kernel') != self.kernel_signature():
            raise UnsafeState('The installed kernel changed after the update')
        if receipt.get('updater') != self.updater_signature():
            raise UnsafeState('The updater changed after the kernel installation')
        version = self.command('/sbin/sysctl', '-n', 'kern.version')
        if not version or version.encode() not in (self.root / 'boot/kernel/kernel').read_bytes():
            raise UnsafeState('The running and installed kernel builds differ')
        self.loader_default()
        self.boot_environment()
        owned = receipt.get('backups', {})
        if set(owned) != set(self.backups):
            raise UnsafeState('The backup ownership record is incomplete')
        for name in self.backups:
            if self.signature(name) != owned[name]:
                raise UnsafeState('A kernel backup changed after installation')
        if require_boot:
            try:
                boot = self.read(self.boot_receipt)
            except FileNotFoundError:
                raise UnsafeState('Normal startup of this kernel has not been confirmed')
            identity = (boot.get('boot'), boot.get('root'), boot.get('generation'))
            if identity != (self.boot_id(), self.root_id(), receipt.get('generation')):
                raise UnsafeState('Normal startup of this kernel has not been confirmed')
        return receipt

    def boot(self):
        receipt = self.verify(require_boot=False)
        self.write(self.boot_receipt, {
            'boot': self.boot_id(), 'root': self.root_id(), 'generation': receipt['generation'],
            'started': time.monotonic()
        })

    def settings(self):
        firmware = ET.parse(self.root / 'conf/config.xml').getroot().find('system/firmware')
        if firmware is None:
            return False, 24
        delay = int(firmware.findtext('kernel_cleanup_delay', '24'))
        if not 1 <= delay <= 720:
            raise UnsafeState('Kernel cleanup delay is outside the supported range')
        return firmware.findtext('kernel_cleanup', '0') == '1', delay

    def inventory(self):
        entries = []
        for path in sorted((self.root / 'boot').iterdir()):
            if path.is_dir() and (path / 'kernel').is_file():
                entries.append({
                    'path': '/' + str(path.relative_to(self.root)).replace(os.sep, '/'),
                    'standard': path.name in ('kernel', 'kernel.old')
                })
            elif path.is_file() and path.name.startswith('kernel'):
                with path.open('rb') as stream:
                    if stream.read(4) == b'\x7fELF':
                        entries.append({
                            'path': '/' + str(path.relative_to(self.root)).replace(os.sep, '/'),
                            'standard': False
                        })
        return entries

    def status(self):
        response = {'ready': False, 'bytes': 0, 'kernels': self.inventory()}
        try:
            receipt = self.verify()
            response['generation'] = receipt['generation']
            response['bytes'] = sum(item['bytes'] for item in receipt['backups'].values() if item)
            response['ready'] = response['bytes'] > 0
            response['reason'] = 'Ready' if response['ready'] else 'No managed backup is present'
        except (UnsafeState, FileNotFoundError) as error:
            response['reason'] = str(error)
        return response

    def clean(self, automatic=False, generation=None):
        if automatic:
            enabled, delay = self.settings()
            if not enabled:
                return {'status': 'skipped', 'reason': 'Automatic cleanup is disabled'}
        receipt = self.verify()
        if not automatic and generation != receipt['generation']:
            raise UnsafeState('The kernel backup changed; refresh its status before confirming cleanup')
        if not any(receipt['backups'].values()):
            return {'status': 'skipped', 'reason': 'No managed backup is present'}
        if automatic:
            boot = self.read(self.boot_receipt)
            if time.monotonic() - boot['started'] < delay * 3600:
                return {'status': 'skipped', 'reason': 'The continuous uptime delay has not elapsed'}
        # No directory paths from the receipt or the API are used for deletion.
        for name in self.backups:
            if receipt['backups'][name] is not None:
                shutil.rmtree(self.root / name)
                receipt['backups'][name] = None
                self.write(self.receipt, receipt)
        return {'status': 'ok', 'reason': 'Managed kernel backups removed'}


@contextmanager
def locks(root):
    import fcntl

    # Match the firmware launcher and lockf -k in opnsense-update, in that order.
    handles = []
    try:
        for name in ('tmp/pkg_upgrade.progress', 'var/run/opnsense-update.lock'):
            descriptor = os.open(root / name, os.O_CREAT | os.O_RDWR | os.O_NOFOLLOW, 0o600)
            handles.append(descriptor)
            fcntl.flock(descriptor, fcntl.LOCK_EX | fcntl.LOCK_NB)
        yield
    finally:
        for descriptor in reversed(handles):
            os.close(descriptor)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('action', choices=('begin', 'record', 'finish', 'boot', 'status', 'clean', 'auto'))
    parser.add_argument('exit_status', nargs='?', type=int)
    parser.add_argument('--generation')
    args = parser.parse_args()
    if not sys.platform.startswith('freebsd'):
        parser.error('This utility requires FreeBSD')
    if os.geteuid() != 0:
        parser.error('This utility requires root privileges')
    manager = KernelCleanup()
    response = {'status': 'ok'}
    try:
        if args.action in ('begin', 'record', 'finish'):
            # The updater holds lockf for the complete transaction, including child updates.
            if args.action == 'finish':
                if args.exit_status is None:
                    raise UnsafeState('The updater exit status is required')
                manager.finish(args.exit_status == 0)
            else:
                getattr(manager, args.action)()
        else:
            with locks(manager.root):
                if args.action == 'boot':
                    manager.boot()
                elif args.action == 'status':
                    response = manager.status()
                else:
                    response = manager.clean(automatic=args.action == 'auto', generation=args.generation)
    except (UnsafeState, OSError, ValueError, KeyError, TypeError, ET.ParseError,
            subprocess.CalledProcessError) as error:
        response = {'status': 'skipped', 'reason': str(error)}
    quiet = ('Automatic cleanup is disabled', 'The continuous uptime delay has not elapsed',
             'No managed backup is present')
    if args.action != 'status' and response.get('status') != 'ok' and response.get('reason') not in quiet:
        subprocess.run(['/usr/bin/logger', '-t', 'kernel-cleanup', response['reason']], check=False)
    elif args.action in ('clean', 'auto') and response.get('status') == 'ok':
        subprocess.run(['/usr/bin/logger', '-t', 'kernel-cleanup', response['reason']], check=False)
    print(json.dumps(response))


if __name__ == '__main__':
    main()
