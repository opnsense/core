"""
    Copyright (c) 2016 Ad Schellevis <ad@opnsense.org>
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
"""
import struct
import unittest
import json
from modules import processhandler


class DummySocket(object):
    """ Simple wrapper to simulate socket client for the processhandler
    """
    def __init__(self):
        """ init
        :return:
        """
        self._send_data = ''
        self._receive_data = []
        self._closed = False

    def setTestData(self, data):
        """ set data to send
        :param data: text
        :return:
        """
        self._closed = False
        self._receive_data = []
        self._send_data = data

    def recv(self, size):
        """ implement sock.rec, flush to self._send_data
        :param size:
        :return:
        """
        return self._send_data.encode()

    def sendall(self, data):
        """ send back to "client"
        :param data: text
        :return:
        """
        self._receive_data.append(data.decode())

    def close(self):
        """ close connection
        :return:
        """
        self._closed = True

    def getReceived(self):
        """ fetch received data
        :return:
        """
        return ''.join(self._receive_data)

    def shutdown(self, mode):
        pass

    def getsockopt(*args, **kwargs):
        # return dummy xucred structure data
        tmp = ('2ih16iP', 0, 0, 3, 0, 0, 5, 1999, 2002, 2012, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1317)
        return struct.pack(*tmp)



class TestCoreMethods(unittest.TestCase):
    def setUp(self):
        """ setup test, load config
        :return:
        """
        self.config_path = '%s/../conf' % '/'.join(__file__.split('/')[:-1])
        self.dummysock = DummySocket()
        self.act_handler = processhandler.ActionHandler(config_path=self.config_path,
                                                        config_environment={})

    def tearDown(self):
        """ end test
        :return:
        """
        self.dummysock = None

    def test_escape_sequence(self):
        """ test if "end of data" is send correctly
        :return:
        """
        # send unknown command
        self.dummysock.setTestData('xxxxxx\n')
        cmd_thread = processhandler.HandlerClient(connection=self.dummysock,
                                                  client_address=None,
                                                  action_handler=self.act_handler)
        cmd_thread.run()
        self.assertEqual(self.dummysock.getReceived()[-4:], '\n%c%c%c' % (chr(0), chr(0), chr(0)), "Invalid sequence")

    def test_command_unknown(self):
        """ test invalid command
        :return:
        """
        self.dummysock.setTestData('xxxxxx\n')
        cmd_thread = processhandler.HandlerClient(connection=self.dummysock,
                                                  client_address=None,
                                                  action_handler=self.act_handler)
        cmd_thread.run()
        self.assertEqual(
            self.dummysock.getReceived().split('\n')[0],
            'Action not allowed or missing',
            'Invalid response'
        )

    def test_configd_actions(self):
        """ request configd command list
        :return:
        """
        self.dummysock.setTestData('configd actions json\n')
        cmd_thread = processhandler.HandlerClient(connection=self.dummysock,
                                                  client_address=None,
                                                  action_handler=self.act_handler)
        cmd_thread.run()
        response = json.loads(self.dummysock.getReceived()[:-4])
        self.assertGreater(len(response), 10, 'number of configd commands very suspicious')
