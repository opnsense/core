"""
    Copyright (c) 2015-2022 Ad Schellevis <ad@opnsense.org>
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

def reverse_log_reader(filename, block_size=81920, start_pos=None):
    """ read log file in reverse order
    :param filename: filename or stream to parse
    :param block_size: max block size to examine per loop
    :param start_pos: start at position in file (None is end of file)
    :return: generator
    """
    if hasattr(filename, 'read') is False:
        input_stream = open(filename, 'r', errors='replace')
    else:
        input_stream = filename

    if start_pos is None:
        input_stream.seek(0, os.SEEK_END)
        start_pos = input_stream.tell()
    file_byte_start = start_pos

    data = ''
    while file_byte_start > 0:
        if file_byte_start - block_size < 0:
            block_size = file_byte_start
            file_byte_start = 0
        else:
            file_byte_start -= block_size

        input_stream.seek(file_byte_start)

        data = input_stream.read(block_size) + data
        # split stream using begin of line (bol) and end of line (eol)
        bol = data.rfind('\n')
        eol = len(data)

        while bol > -1:
            line_end = file_byte_start + eol
            line = data[bol:eol]
            eol = bol
            bol = data.rfind('\n', 0, eol)
            if line_end != start_pos:
                yield {'line': line.strip().strip('\u0000'), 'pos': line_end}

        data = data[:eol] if bol == -1 else ''

        if file_byte_start == 0 and bol == -1:
            yield {'line': data.strip().strip('\u0000'), 'pos': len(data)}
