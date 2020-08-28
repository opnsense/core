"""
    Copyright (c) 2014-2019 Ad Schellevis <ad@opnsense.org>
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

    --------------------------------------------------------------------------------------
    package : configd
    function: configd inline actions

"""

from . import template
from . import config
from . import syslog_debug

__author__ = 'Ad Schellevis'


def execute(action, parameters):
    """ wrapper for inline functions

    :param action: action object ( processhandler.Action type )
    :param parameters: parameter string
    :return: status ( string )
    """
    if action.command == 'template.reload':
        # generate template
        tmpl = template.Template(action.root_dir)
        conf = config.Config(action.config)
        tmpl.set_config(conf.get())
        filenames = tmpl.generate(parameters)

        del conf
        del tmpl

        # send generated filenames to syslog
        if filenames is not None:
            for filename in filenames:
                syslog_debug(' %s generated %s' % (parameters, filename))
            return 'OK'
        else:
            return 'ERR'
    elif action.command == 'template.list':
        # traverse all installed templates and return list
        # the number of registered targets is returned between []
        tmpl = template.Template(action.root_dir)
        retval = []
        for module_name in sorted(tmpl.list_modules()):
            template_count = len(tmpl.list_module(module_name)['+TARGETS'])
            template_name = '%s [%d]' % (module_name, template_count)
            retval.append(template_name)

        del tmpl
        return '\n'.join(retval)
    elif action.command == 'template.cleanup':
        tmpl = template.Template(action.root_dir)
        filenames = tmpl.cleanup(parameters)
        del tmpl

        # send generated filenames to syslog
        if filenames is not None:
            for filename in filenames:
                syslog_debug(' %s removed %s' % (parameters, filename))
            return 'OK'
        else:
            return 'ERR'
    elif action.command == 'configd.actions':
        # list all available configd actions
        from .processhandler import ActionHandler
        act_handler = ActionHandler()
        actions = act_handler.list_actions(['message', 'description'])

        if str(parameters).lower() == 'json':
            import json
            return json.dumps(actions)
        else:
            result = []
            for action in actions:
                result.append('%s [ %s ]' % (action, actions[action]['description']))

            return '\n'.join(result)

    return 'ERR'
