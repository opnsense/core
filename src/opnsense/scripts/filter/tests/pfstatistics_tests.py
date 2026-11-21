import os
import sys
import unittest
from unittest import mock
sys.path.insert(0, "%s/.." % os.path.dirname(__file__))
import pfstatistics

# pfctl -vvsr output (OPNsense 26.7) for a rule that matched and one that never did
MATCHED = '@62 pass out log all flags S/SA keep state allow-opts label "fae559338f65e11c53669fc3642c93c2"'
UNMATCHED = '@1 block drop in log inet from 10.0.2.15 to any'
RULES = MATCHED + """
  [ Evaluations: 39        Packets: 52        Bytes: 3640        States: 26    ]
  [ Source Nodes: 0      Limit: 0      NAT/RDR: 0      Route: 0      ]
  [ Inserted: uid 0 pid 0 State Creations: 26    ]
  [ Last Active Time: Sat Nov 21 23:15:41 2026 ]
""" + UNMATCHED + """
  [ Evaluations: 39        Packets: 0         Bytes: 0           States: 0     ]
  [ Source Nodes: 0      Limit: 0      NAT/RDR: 0      Route: 0      ]
  [ Inserted: uid 0 pid 0 State Creations: 0     ]
  [ Last Active Time: N/A ]
"""


class TestPfctlRules(unittest.TestCase):
    def rules(self):
        def run(cmd, **kwargs):
            return mock.Mock(stdout=RULES if cmd[-1] == '-vvsrules' else '')
        with mock.patch.object(pfstatistics.subprocess, 'run', side_effect=run):
            return pfstatistics.pfctl_rules()['filter rules']

    def test_counters(self):
        rule = self.rules()[MATCHED]
        self.assertEqual(rule['evaluations'], 39)
        self.assertEqual(rule['packets'], 52)
        self.assertEqual(rule['bytes'], 3640)
        self.assertEqual(rule['states'], 26)
        self.assertEqual(rule['route'], 0)
        self.assertEqual(rule['state_creations'], 26)

    def test_last_active_time(self):
        rules = self.rules()
        self.assertEqual(rules[MATCHED]['time'], 'sat nov 21 23:15:41 2026')
        self.assertNotIn('23', rules[MATCHED])
        self.assertEqual(rules[UNMATCHED]['time'], 'n/a')


if __name__ == '__main__':
    unittest.main()
