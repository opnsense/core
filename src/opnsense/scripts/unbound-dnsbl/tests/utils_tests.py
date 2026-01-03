import collections
import unittest
import sys
import os
sys.path.insert(0, "%s/../lib" % os.path.dirname(__file__))
from lib.utils import obj_path_exists

class TestUtilsMethods(unittest.TestCase):
    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        self._test_data = collections.namedtuple('dummy', 'level1')
        self._test_data.level1 = collections.namedtuple('dummy', 'level2')
        self._test_data.level1.level2 = 'data'

    def test_obj_path_exists_true(self):
        self.assertTrue(obj_path_exists(self._test_data, 'level1.level2'), 'Unable to map level1.level2')

    def test_obj_path_exists_false(self):
        self.assertFalse(obj_path_exists(self._test_data, 'level1.level2x'), 'Node level1.level2x should not exist')
