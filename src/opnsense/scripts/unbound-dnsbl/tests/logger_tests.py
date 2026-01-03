import unittest
import tempfile
import sys
import os
sys.path.insert(0, "%s/../lib" % os.path.dirname(__file__))
from lib.log import Logger

class TestLoggerMethods(unittest.TestCase):
    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        self._target_dir = tempfile.gettempdir()
        self._statfile = '%s/stats' % self._target_dir

    def test_disabled(self):
        if os.path.exists(self._statfile):
            os.remove(self._statfile)
        logger = Logger(self._target_dir)
        self.assertFalse(logger.stats_enabled, 'Unable to map level1.level2')

    def test_enabled(self):
        open(self._statfile, "w").close()
        logger = Logger(self._target_dir)
        self.assertTrue(logger.stats_enabled, 'Unable to map level1.level2')
