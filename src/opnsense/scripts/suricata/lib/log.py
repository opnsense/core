"""
    Copyright (c) 2015 Ad Schellevis

    part of OPNsense (https://www.opnsense.org/)

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
import os

def reverse_log_reader(filename, block_size = 8192):
    """ read log file in reverse order
    :param filename: filename to parse
    :param block_size: max block size to examine per loop
    :return: generator
    """
    with open(filename,'rU') as f_in:
        f_in.seek(0, os.SEEK_END)
        file_byte_start = f_in.tell()

        data = ''
        while True:
            if file_byte_start-block_size < 0:
                block_size = block_size - file_byte_start
                file_byte_start = 0
            else:
                file_byte_start -= block_size

            f_in.seek(file_byte_start)
            data = f_in.read(block_size) + data

            eol = data.rfind('\n')
            while eol > -1:
                line = data[eol:]
                data = data[:eol]
                eol = data.rfind('\n')
                yield line.strip()

            if file_byte_start == 0:
                break

