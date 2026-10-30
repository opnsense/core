# SPDX-License-Identifier: BSD-2-Clause

import importlib.util
import os
from pathlib import Path
import shutil
import subprocess
import sys
import tempfile
import unittest
from unittest.mock import patch


HELPER = Path(__file__).parents[2] / 'src/opnsense/scripts/firmware/kernel_cleanup.py'
spec = importlib.util.spec_from_file_location('kernel_cleanup', HELPER)
module = importlib.util.module_from_spec(spec)
spec.loader.exec_module(module)


class FakeSystem(module.KernelCleanup):
    current_boot = 'boot-1'
    bootfile = '/boot/kernel/kernel'
    kernel_version = 'FreeBSD test-build-1\n    builder@test:/obj/kernel'
    mount_source = '/dev/gpt/root'
    filesystem = 'ufs'
    environments = 'default\tNR\t/\t1G\t2026-10-30'
    module_path = '/boot/kernel;/boot/modules'

    def command(self, *args):
        if args[0] == '/sbin/mount':
            return f'{self.mount_source} / {self.filesystem} rw 0 0'
        if args[0] == '/sbin/bectl':
            return self.environments
        values = {
            'kern.boottime': self.current_boot,
            'kern.bootfile': self.bootfile,
            'kern.version': self.kernel_version,
            'kern.module_path': self.module_path
        }
        return values[args[-1]]


class KernelCleanupTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name).resolve()
        self.manager = FakeSystem(self.root)
        for name in ('boot/kernel', 'boot/kernel.old', 'boot/defaults', 'boot/loader.conf.d',
                     'usr/lib/debug/boot/kernel.old', 'usr/local/opnsense/version',
                     'usr/local/sbin', 'var/run', 'var/cache/opnsense-update', 'conf', 'tmp'):
            (self.root / name).mkdir(parents=True, exist_ok=True)
        self.put('boot/kernel/kernel', b'\x7fELF\0' + self.manager.kernel_version.encode() + b'\0')
        self.put('boot/kernel.old/kernel', b'previous kernel')
        self.put('boot/kernel.old/driver.ko', b'previous module')
        self.put('usr/lib/debug/boot/kernel.old/kernel.debug', b'debug symbols')
        self.put('usr/local/sbin/opnsense-update', b'#!/bin/sh\n# kernel-cleanup-protocol: 1\n')
        self.put('boot/defaults/loader.conf', 'kernel="kernel"\nbootfile="kernel"\n')
        self.configure()
        self.clock = 100.0
        self.addCleanup(patch.stopall)
        patch.object(module.time, 'monotonic', lambda: self.clock).start()
        patch.dict(os.environ, {'OPNSENSE_KERNEL_TRANSACTION': '11111111-1111-1111-1111-111111111111'}).start()

    def put(self, name, value):
        path = self.root / name
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_bytes(value if isinstance(value, bytes) else value.encode())

    def configure(self, enabled=False, delay=24):
        self.put('conf/config.xml', '<opnsense><system><firmware>'
                 f'<kernel_cleanup>{int(enabled)}</kernel_cleanup>'
                 f'<kernel_cleanup_delay>{delay}</kernel_cleanup_delay>'
                 '</firmware></system></opnsense>')

    def install(self, boot=True):
        self.manager.begin()
        self.manager.record()
        self.manager.finish(True)
        if boot:
            number = int(self.manager.current_boot.split('-')[1]) + 1
            self.manager.current_boot = f'boot-{number}'
            self.manager.boot()

    def clean(self):
        generation = self.manager.read(self.manager.receipt)['generation']
        return self.manager.clean(generation=generation)

    def assertBlocked(self, operation=None):
        with self.assertRaises((module.UnsafeState, FileNotFoundError, ValueError)):
            (operation or self.clean)()
        self.assertTrue((self.root / 'boot/kernel/kernel').exists())
        self.assertTrue((self.root / 'boot/kernel.old/kernel').exists())

    def symlink(self, target, link, directory=False):
        try:
            (self.root / link).symlink_to(self.root / target, target_is_directory=directory)
        except OSError as error:
            self.skipTest(f'Symbolic links require host support: {error}')

    def test_manual_cleanup_preserves_three_additional_kernels(self):
        self.install()
        for name in ('kernel.good', 'test-build', 'kernel.backup'):
            self.put(f'boot/{name}/kernel', b'user kernel')
        status = self.manager.status()
        self.assertTrue(status['ready'])
        self.assertEqual(len(status['kernels']), 5)
        self.assertEqual(self.clean()['status'], 'ok')
        self.assertFalse((self.root / 'boot/kernel.old').exists())
        self.assertFalse((self.root / 'usr/lib/debug/boot/kernel.old').exists())
        for name in ('kernel', 'kernel.good', 'test-build', 'kernel.backup'):
            self.assertTrue((self.root / f'boot/{name}/kernel').exists())
        self.assertEqual(self.clean()['status'], 'skipped')

    def test_automatic_cleanup_is_disabled_by_default(self):
        self.install()
        self.clock += 1000000
        self.assertEqual(self.manager.clean(automatic=True)['status'], 'skipped')
        self.assertTrue((self.root / 'boot/kernel.old').exists())

    def test_standalone_additional_kernel_image_is_preserved(self):
        self.install()
        self.put('boot/kernel.test', b'\x7fELFcustom kernel')
        paths = [entry['path'] for entry in self.manager.inventory()]
        self.assertIn('/boot/kernel.test', paths)
        self.clean()
        self.assertTrue((self.root / 'boot/kernel.test').exists())

    def test_automatic_cleanup_waits_for_continuous_uptime(self):
        self.configure(True)
        self.install()
        self.clock += 24 * 3600 - 1
        self.assertEqual(self.manager.clean(automatic=True)['status'], 'skipped')
        self.clock += 1
        self.assertEqual(self.manager.clean(automatic=True)['status'], 'ok')

    def test_reboot_requires_startup_confirmation_and_restarts_delay(self):
        self.configure(True)
        self.install()
        self.clock += 100000
        self.manager.current_boot = 'boot-3'
        self.assertBlocked()
        self.manager.boot()
        self.assertEqual(self.manager.clean(automatic=True)['status'], 'skipped')

    def test_installing_boot_cannot_be_confirmed(self):
        self.install(boot=False)
        self.assertBlocked(self.manager.boot)

    def test_old_or_custom_running_kernel_is_preserved(self):
        self.install()
        for path in ('/boot/kernel.old/kernel', '/boot/kernel.good/kernel'):
            with self.subTest(path=path):
                self.manager.bootfile = path
                self.assertBlocked()

    def test_same_release_different_kernel_build_is_rejected(self):
        self.install()
        self.manager.kernel_version = 'FreeBSD test-build-2'
        self.assertBlocked()

    def test_modified_installed_kernel_is_rejected(self):
        self.install()
        self.put('boot/kernel/kernel', b'another installed kernel')
        self.assertBlocked()

    def test_modified_backup_module_is_preserved(self):
        self.install()
        self.put('boot/kernel.old/driver.ko', b'user modifications')
        self.assertBlocked()

    def test_added_backup_file_is_preserved(self):
        self.install()
        self.put('boot/kernel.old/user.ko', b'user module')
        self.assertBlocked()

    def test_replaced_directory_with_identical_files_is_preserved(self):
        self.install()
        original = self.root / 'boot/kernel.old'
        replacement = self.root / 'boot/replacement'
        shutil.copytree(original, replacement)
        shutil.rmtree(original)
        replacement.rename(original)
        self.assertBlocked()

    def test_symbolic_link_backup_is_preserved(self):
        self.install()
        (self.root / 'boot/kernel.old').rename(self.root / 'boot/saved')
        self.symlink('boot/saved', 'boot/kernel.old', True)
        self.assertBlocked()

    def test_symbolic_link_inside_backup_is_preserved(self):
        self.install()
        self.put('conf/important', b'keep')
        self.symlink('conf/important', 'boot/kernel.old/linked.ko')
        self.assertBlocked()
        self.assertEqual((self.root / 'conf/important').read_bytes(), b'keep')

    def test_every_deferred_stage_blocks_cleanup(self):
        self.install()
        for name in ('kernel', 'base', 'pkgs'):
            with self.subTest(stage=name):
                marker = self.root / f'var/cache/opnsense-update/.{name}.pending'
                marker.touch()
                self.assertBlocked()
                marker.unlink()

    def test_failed_transaction_cannot_be_laundered_by_cache_cleanup(self):
        self.install()
        self.manager.begin()
        self.manager.finish(False)
        self.manager.begin()
        self.manager.finish(True)
        self.assertBlocked()

    def test_abandoned_transaction_remains_blocked(self):
        self.install()
        self.manager.begin()
        self.manager.begin()
        self.manager.finish(True)
        self.assertBlocked()

    def test_deferred_major_upgrade_becomes_ready_after_final_stage(self):
        marker = self.root / 'var/cache/opnsense-update/.pkgs.pending'
        marker.touch()
        self.install(boot=False)
        self.assertFalse(self.manager.read(self.manager.receipt)['complete'])
        self.manager.current_boot = 'boot-2'
        self.manager.begin()
        marker.unlink()
        self.manager.finish(True)
        self.manager.boot()
        self.assertTrue(self.manager.status()['ready'])

    def test_failed_final_major_upgrade_stage_is_preserved(self):
        marker = self.root / 'var/cache/opnsense-update/.pkgs.pending'
        marker.touch()
        self.install(boot=False)
        self.manager.current_boot = 'boot-2'
        self.manager.begin()
        marker.unlink()
        self.manager.finish(False)
        self.assertBlocked(self.manager.boot)

    def test_durable_sentinel_blocks_cleanup_when_callback_failed(self):
        self.install()
        (self.manager.version / 'kernel-cleanup.pending').touch()
        self.assertBlocked()

    def test_next_boot_kernel_override_is_preserved(self):
        self.install()
        self.put('boot/loader.conf.local', 'kernel="kernel.old"\n')
        self.assertBlocked()

    def test_loader_directory_override_is_preserved(self):
        self.install()
        self.put('boot/loader.conf.d/custom.conf', 'bootfile="/boot/test-build/kernel"\n')
        self.assertBlocked()

    def test_custom_loader_include_is_preserved(self):
        self.install()
        self.put('boot/loader.conf', 'loader_conf_files="/conf/custom-loader"\n')
        self.assertBlocked()

    def test_scripted_loader_configuration_requires_manual_review(self):
        self.install()
        self.put('boot/loader.conf', 'include /conf/custom-loader\n')
        self.assertBlocked()

    def test_nextboot_file_is_preserved(self):
        self.install()
        self.put('boot/nextboot.conf', 'kernel="kernel.old"\n')
        self.assertBlocked()

    def test_module_search_path_using_backup_is_preserved(self):
        self.install()
        self.manager.module_path = '/boot/kernel.old;/boot/modules'
        self.assertBlocked()

    def test_next_boot_module_search_path_using_backup_is_preserved(self):
        self.install()
        self.put('boot/loader.conf', 'module_path="/boot/kernel.old;/boot/modules"\n')
        self.assertBlocked()

    def test_driver_explicitly_loaded_from_backup_is_preserved(self):
        self.install()
        self.put('boot/loader.conf', 'custom_name="/boot/kernel.old/driver.ko"\ncustom_load="YES"\n')
        self.assertBlocked()

    def test_module_path_alias_to_backup_is_preserved(self):
        self.install()
        self.symlink('boot/kernel.old', 'boot/alias', True)
        self.manager.module_path = '/boot/alias;/boot/modules'
        self.assertBlocked()

    def test_loader_command_hook_is_preserved(self):
        self.install()
        self.put('boot/loader.conf', 'kernel_before="load /boot/kernel.old/kernel"\n')
        self.assertBlocked()

    def test_standard_loader_banner_is_supported(self):
        self.put('boot/defaults/loader.conf',
                 'exec="echo Loading /boot/defaults/loader.conf"\nkernel="kernel"\nbootfile="kernel"\n')
        self.install()
        self.assertTrue(self.manager.status()['ready'])

    def test_zfs_temporary_or_different_next_environment_is_preserved(self):
        self.manager.filesystem = 'zfs'
        self.manager.mount_source = 'zroot/ROOT/default'
        self.install()
        self.assertTrue(self.manager.status()['ready'])
        self.manager.environments = 'default\tN\t/\t1G\tdate\nother\tR\t-\t1G\tdate'
        self.assertBlocked()

    def test_boot_environment_change_is_preserved(self):
        self.install()
        self.manager.mount_source = '/dev/gpt/other-root'
        self.assertBlocked()

    def test_unsupported_root_filesystem_is_preserved(self):
        self.install()
        self.manager.filesystem = 'unionfs'
        self.assertBlocked()

    def test_separate_mount_inside_backup_is_preserved(self):
        self.install()
        original = Path.is_mount
        backup = self.root / 'boot/kernel.old'
        with patch.object(Path, 'is_mount', lambda path: path == backup or original(path)):
            self.assertBlocked()

    def test_old_updater_or_changed_updater_is_preserved(self):
        self.install()
        self.put('usr/local/sbin/opnsense-update', b'old updater without locking')
        self.assertBlocked()

    def test_successful_package_stage_can_replace_a_compatible_updater(self):
        self.install(boot=False)
        self.manager.begin()
        self.put('usr/local/sbin/opnsense-update', b'#!/bin/sh\n# kernel-cleanup-protocol: 1\n# new version\n')
        self.manager.finish(True)
        self.manager.current_boot = 'boot-2'
        self.manager.boot()
        self.assertTrue(self.manager.status()['ready'])

    def test_successful_downgrade_to_incompatible_updater_remains_blocked(self):
        self.install()
        self.manager.begin()
        self.put('usr/local/sbin/opnsense-update', b'#!/bin/sh\n# older updater\n')
        with self.assertRaises(module.UnsafeState):
            self.manager.finish(True)
        self.assertBlocked()

    def test_core_helper_first_installed_during_the_update_can_record_kernel(self):
        # The updater creates this sentinel before installing a new Core package.
        (self.manager.version / 'kernel-cleanup.pending').touch()
        self.manager.record()
        self.manager.finish(True)
        self.manager.current_boot = 'boot-2'
        self.manager.boot()
        self.assertTrue(self.manager.status()['ready'])

    def test_stale_manual_confirmation_cannot_remove_new_backup(self):
        self.install()
        generation = self.manager.status()['generation']
        self.install()
        self.assertBlocked(lambda: self.manager.clean(generation=generation))

    def test_preexisting_unowned_backup_is_preserved(self):
        self.assertFalse(self.manager.status()['ready'])
        self.assertBlocked()

    def test_corrupt_receipt_is_preserved(self):
        self.install()
        self.manager.receipt.write_text('{')
        self.assertBlocked()

    def test_malformed_completion_flag_is_preserved(self):
        self.install()
        receipt = self.manager.read(self.manager.receipt)
        receipt['complete'] = 'false'
        self.manager.write(self.manager.receipt, receipt)
        self.assertBlocked()

    def test_unknown_backup_paths_in_receipt_are_rejected(self):
        self.install()
        receipt = self.manager.read(self.manager.receipt)
        receipt['backups']['conf'] = receipt['backups'].pop('boot/kernel.old')
        self.manager.write(self.manager.receipt, receipt)
        self.assertBlocked()

    def test_missing_debug_backup_is_supported(self):
        shutil.rmtree(self.root / 'usr/lib/debug/boot/kernel.old')
        self.install()
        self.assertEqual(self.clean()['status'], 'ok')

    def test_absent_managed_backups_are_idempotent(self):
        for name in self.manager.backups:
            shutil.rmtree(self.root / name)
        self.install()
        self.assertFalse(self.manager.status()['ready'])
        self.assertEqual(self.clean()['status'], 'skipped')

    def test_invalid_delay_is_rejected(self):
        self.install()
        self.configure(True, 0)
        self.assertBlocked(lambda: self.manager.clean(automatic=True))

    def test_transaction_tokens_must_match(self):
        self.manager.begin()
        with patch.dict(os.environ, {'OPNSENSE_KERNEL_TRANSACTION': '22222222-2222-2222-2222-222222222222'}):
            with self.assertRaises(module.UnsafeState):
                self.manager.record()

    def test_io_error_preserves_running_kernel(self):
        self.install()
        with patch.object(module.shutil, 'rmtree', side_effect=PermissionError('read only filesystem')):
            with self.assertRaises(PermissionError):
                self.clean()
        self.assertTrue((self.root / 'boot/kernel/kernel').exists())
        self.assertTrue((self.root / 'boot/kernel.old/kernel').exists())

    @unittest.skipIf(sys.platform == 'win32', 'Requires POSIX flock and O_NOFOLLOW')
    def test_native_update_lock_contention_and_release(self):
        import fcntl

        path = self.root / 'var/run/opnsense-update.lock'
        with path.open('w') as stream:
            fcntl.flock(stream, fcntl.LOCK_EX | fcntl.LOCK_NB)
            with self.assertRaises(BlockingIOError):
                with module.locks(self.root):
                    self.fail('Cleanup acquired an update lock already held by another descriptor')
        inode = path.stat().st_ino
        with module.locks(self.root):
            pass
        self.assertEqual(path.stat().st_ino, inode)

    @unittest.skipIf(sys.platform == 'win32', 'Requires POSIX flock and O_NOFOLLOW')
    def test_native_firmware_lock_contention(self):
        import fcntl

        with (self.root / 'tmp/pkg_upgrade.progress').open('w') as stream:
            fcntl.flock(stream, fcntl.LOCK_EX | fcntl.LOCK_NB)
            with self.assertRaises(BlockingIOError):
                with module.locks(self.root):
                    self.fail('Cleanup acquired the firmware launcher lock')

    @unittest.skipUnless(sys.platform.startswith('freebsd'), 'Requires the FreeBSD lockf utility')
    def test_native_freebsd_lockf_interoperability(self):
        path = self.root / 'var/run/opnsense-update.lock'
        process = subprocess.Popen([
            '/usr/bin/lockf', '-k', '-t', '0', str(path), sys.executable, '-c',
            'import sys; print("locked", flush=True); sys.stdin.read()'
        ], stdin=subprocess.PIPE, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
        try:
            self.assertEqual(process.stdout.readline().strip(), 'locked')
            with self.assertRaises(BlockingIOError):
                with module.locks(self.root):
                    self.fail('Cleanup acquired a lock already held by lockf')
        finally:
            process.communicate('', timeout=10)


if __name__ == '__main__':
    unittest.main()
