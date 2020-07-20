"""
    Copyright (c) 2020 Ad Schellevis <ad@opnsense.org>
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
import glob
import importlib
import sys

class BaseLogFormat:
    """ Log format handler
    """
    def __init__(self, filename):
        self._filename = filename
        self._priority = 255

    @property
    def name(self):
        return self.__class__.__name__

    @property
    def prio(self):
        """ priority, lower means more important
        """
        return self._priority

    @staticmethod
    def match(line):
        """ Does this formatter fit for the provided line
        """
        return False

    @staticmethod
    def timestamp(line):
        """ Extract timestamp from line
        """
        pass

    @staticmethod
    def line(line):
        """ Return line (without timestamp)
        """
        return line

    @staticmethod
    def process_name(line):
        """ Return process name
        """
        return ""


class FormatContainer:
    def __init__(self, filename):
        self._handlers = list()
        self._filename = filename
        self._register()

    def _register(self):
        all_handlers = list()
        for filename in glob.glob("%s/*.py" % os.path.dirname(__file__)):
            if not filename.endswith('__init__.py'):
                module_name = ".%s" % os.path.splitext(os.path.basename(filename))[0]
                importlib.import_module(module_name, "logformats")

        for module_name in dir(sys.modules['logformats']):
            for attribute_name in dir(getattr(sys.modules['logformats'], module_name)):
                cls = getattr(getattr(sys.modules['logformats'], module_name), attribute_name)
                if isinstance(cls, type) and issubclass(cls, BaseLogFormat) and cls != BaseLogFormat:
                    all_handlers.append(cls(self._filename))

        self._handlers = sorted(all_handlers, key=lambda k: k.prio)

    def get_format(self, line):
        for handler in self._handlers:
            if handler.match(line):
                return handler
