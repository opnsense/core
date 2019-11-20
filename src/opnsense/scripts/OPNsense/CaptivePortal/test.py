#!/usr/local/bin/python3

import sys
import time
import syslog
import traceback
import subprocess
sys.path.insert(0, "/usr/local/opnsense/site-python")
from lib import Config
from lib.db import DB
from lib.arp import ARP
from lib.ipfw import IPFW
from lib.daemonize import Daemonize
from sqlite3_helper import check_and_repair


#print (IPFW().list_accounting_info())
#IPFW().add_to_table(1, '10.211.52.6')
#IPFW().delete_from_table(1, '10.211.52.6')
#print (IPFW().list_table(1))

print (ARP().list_items())

sys.exit(0)
db = DB()
cur = db._connection.cursor()
cur.execute("""select   cc.ip_address,  cc.zoneid, cc.sessionId, cc.deleted
               from     cp_clients cc
               --where    cc.deleted = 0
            """)

for row in cur.fetchall():
    print (row)
