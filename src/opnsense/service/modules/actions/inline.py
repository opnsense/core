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
import traceback
from .. import template
from .. import config
from .. import syslog_debug, syslog_error
from .base import BaseAction


class Action(BaseAction):
    def __init__(self, config_environment: dict, action_parameters: dict):
        super().__init__(config_environment, action_parameters)
        self.root_dir = action_parameters.get('root_dir', None)
        self.config = action_parameters.get('config', None)

    def execute(self, parameters, message_uuid, *args, **kwargs):
        super().execute(parameters, message_uuid, *args, **kwargs)
        try:
            # match parameters, serialize to parameter string defined by action template
            if len(parameters) > 0:
                act_parameters = self.parameters % tuple(parameters)
            else:
                act_parameters = ''

            if self.command == 'template.reload':
                # generate template
                tmpl = template.Template(self.root_dir)
                conf = config.Config(self.config)
                tmpl.set_config(conf.get())
                filenames = tmpl.generate(act_parameters)

                del conf
                del tmpl

                # send generated filenames to syslog
                if filenames is not None:
                    for filename in filenames:
                        syslog_debug(' %s generated %s' % (act_parameters, filename))
                    return 'OK'
                else:
                    return 'ERR'
            elif self.command == 'template.list':
                # traverse all installed templates and return list
                # the number of registered targets is returned between []
                tmpl = template.Template(self.root_dir)
                retval = []
                for module_name in sorted(tmpl.list_modules()):
                    template_count = len(tmpl.list_module(module_name)['+TARGETS'])
                    template_name = '%s [%d]' % (module_name, template_count)
                    retval.append(template_name)

                del tmpl
                return '\n'.join(retval)
            elif self.command == 'template.cleanup':
                tmpl = template.Template(self.root_dir)
                filenames = tmpl.cleanup(act_parameters)
                del tmpl

                # send generated filenames to syslog
                if filenames is not None:
                    for filename in filenames:
                        syslog_debug(' %s removed %s' % (act_parameters, filename))
                    return 'OK'
                else:
                    return 'ERR'
            elif self.command == 'configd.actions':
                # list all available configd actions
                from ..processhandler import ActionHandler
                act_handler = ActionHandler()
                actions = act_handler.list_actions(['message', 'description'])

                if str(act_parameters).lower() == 'json':
                    import json
                    return json.dumps(actions)
                else:
                    result = []
                    for action in actions:
                        result.append('%s [ %s ]' % (action, actions[action]['description']))

                    return '\n'.join(result)

            return 'ERR'
        except Exception as inline_exception:
            syslog_error('[%s] Inline action failed with %s at %s' % (
                message_uuid, inline_exception, traceback.format_exc()
            ))
            return 'Execute error'
