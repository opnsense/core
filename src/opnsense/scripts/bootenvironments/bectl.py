#!/usr/bin/env python
"""
    Copyright (c) 2024 Sheridan Computers Limited
    All rights reserved.

    Redistribution and use in source and binary forms, with or without
    modification, are permitted provided that the following conditions are met:

    1. Redistributions of source code must retain the above copyright notice,
     this list of conditions and the following disclaimer.

    2. Redistributions in binary form must reproduce the above copyright
     notice, this list of conditions and the following disclaimer in the
     documentation and/or other materials provided with the distribution.

    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
    POSSIBILITY OF SUCH DAMAGE.
"""
from subprocess import Popen, run, PIPE

def activate_be(be_name: str, t: bool = False) -> bool:
    """
    This function activate a BE.
    :param be_name: Name of the BE to activate.
    :param t: If True, the BE will be activated even if it is mounted.
    """
    option = '-t' if t else ''
    cmd_list = ['bectl', 'activate', be_name]
    if option == '-t':
        cmd_list.insert(2, option)
    bectl_process = run(cmd_list, stdout=PIPE)
    return bectl_process.returncode == 0

def create_be(new_be_name: str, non_active_be: str = None, recursive: bool = False) -> bool:
    """
    This function create a BE.
    :param new_be_name: Name of the new BE.
    :param non_active_be: Name of the non active BE.
    :param recursive: If True, the BE will be created recursively.
    """
    cmd_list = ['bectl', 'create']
    if recursive is True:
        cmd_list.append('-r')
    if non_active_be is not None:
        cmd_list.append('-e')
        cmd_list.append(non_active_be.strip())
    cmd_list.append(new_be_name)
    bectl_process = run(cmd_list)
    return bectl_process.returncode == 0

def destroy_be(be_name: str, F: bool = False, o: bool = False):
    """
    This function destroy a BE.
    :param be_name: Name of the BE to destroy.
    :param F: If True, the BE will be destroyed even if it is active.
    :param o: If True, the BE will be destroyed even if it is mounted.
    """
    option = '-'
    option += 'F' if F else ''
    option += 'o' if o else ''
    cmd_list = ['bectl', 'destroy', be_name]
    if option != '-':
        cmd_list.insert(2, option)
    bectl_process = run(cmd_list)
    return bectl_process.returncode == 0

def rename_be(original_be_name: str, new_be_name: str):
    """
    This function rename a BE.
    :param original_be_name: Name of the BE to rename.
    :param new_be_name: New name of the BE.
    """
    cmd_list = ['bectl', 'rename', original_be_name, new_be_name]
    bectl_process = run(cmd_list)
    return bectl_process.returncode == 0

def mount_be(be_name: str, path: str = None) -> str:
    """
    This function mounts the BE.
    :param be_name: Name of the BE to mount.
    :param path: The path where the BE will be mounted. If not provided,
    a bectl will create a random one.
    :return: The path where the BE is mounted.
    """
    cmd_list = ['bectl', 'mount', be_name]
    cmd_list.append(path) if path else None
    bectl_process = run(
        cmd_list,
        universal_newlines=True,
        encoding='utf-8'
    )
    assert bectl_process.returncode == 0
    return bectl_process.stdout.strip()

def umount_be(be_name: str):
    """
    This function unmount the BE.
    :param be_name: Name of the BE to unmount.
    """
    cmd_list = ['bectl', 'umount', be_name]
    bectl_process = run(cmd_list)
    return bectl_process.returncode == 0

def get_be_list() -> list:
    """
    This function get the list of BEs.
    :return: A list of BEs.
    """
    cmd_list = ['bectl', 'list', '-H']
    bectl_output: Popen[str] = Popen(
        cmd_list,
        stdout=PIPE,
        close_fds=True,
        universal_newlines=True,
        encoding='utf-8'
    )
    bectl_list = bectl_output.stdout.read().splitlines()
    return bectl_list

def is_file_system_zfs() -> bool:
    """
    This function check if the file system is zfs.
    :return: True if the file system is zfs, False otherwise.
    """
    cmd_list = ['df', '-Tt', 'zfs', '/']
    df_output = run(cmd_list, stdout=PIPE)
    return df_output.returncode == 0