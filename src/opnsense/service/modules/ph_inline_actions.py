"""
    Copyright (c) 2014 Ad Schellevis

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

    --------------------------------------------------------------------------------------
    package : check_reload_status
    function: unix domain socket process worker process


"""
__author__ = 'Ad Schellevis'

import syslog


def execute(action,parameters):
    """ wrapper for inline functions

    :param action: action object ( processhandler.Action type )
    :param parameters: parameter string
    :return: status ( string )
    """
    if  action.command == 'template.reload':
        import template
        import config
        tmpl = template.Template()
        conf = config.Config(action.config)
        tmpl.setConfig(conf.get())
        filenames = tmpl.generate(parameters)

        # send generated filenames to syslog
        for filename in filenames:
            syslog.syslog(syslog.LOG_DEBUG,' %s generated %s' % ( parameters, filename ) )

        del conf
        del tmpl

        return 'OK'

    return 'ERR'