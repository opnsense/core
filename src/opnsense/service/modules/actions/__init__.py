"""
    Copyright (c) 2023 Ad Schellevis <ad@opnsense.org>
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
import glob
import importlib
import sys
import os
from .base import BaseAction


class ActionFactory:
    def __init__(self):
        self.action_types = {}
        for filename in glob.glob("%s/*.py" % os.path.dirname(__file__)):
            importlib.import_module(".%s" % os.path.splitext(os.path.basename(filename))[0], __name__)

        for module_name in dir(sys.modules[__name__]):
            for attribute_name in dir(getattr(sys.modules[__name__], module_name)):
                cls = getattr(getattr(sys.modules[__name__], module_name), attribute_name)
                if isinstance(cls, type) and issubclass(cls, BaseAction) and cls is not BaseAction:
                    self.action_types[module_name.lower()] = cls

    def get(self, environment: dict, conf: dict):
        target_act = conf.get('type', 'none')
        if target_act not in self.action_types:
            # no valid target action type found, return none type
            target_act = 'none'

        return self.action_types[target_act](config_environment=environment, action_parameters=conf)
