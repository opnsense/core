#!/usr/local/bin/python3
 
"""
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
    add route for delegated prefix
"""
import subprocess
import sys
import argparse
import ipaddress
import re

def mac_address(addr):
    print("checking mac: " + addr)
    if None == re.fullmatch('^[a-fA-F0-9]{2}(:[a-fA-F0-9]{2}){5}$', addr):
        raise ValueError("invalid mac address")
    return addr

if __name__ == '__main__':
    # parse input arguments
    parser = argparse.ArgumentParser()
    parser.add_argument('hardware', help='mac address', type=mac_address)
    parser.add_argument('prefix', help='delegated prefix', type=ipaddress.IPv6Network)
    inputargs = parser.parse_args()

    macaddr = inputargs.hardware
    prefix = inputargs.prefix
    if prefix.is_link_local:
        print("Error: Attempting to use a link-local prefix.")
        sys.exit(1)
    if prefix.is_loopback:
        print("Error: Attempting to use loopback prefix.")
        sys.exit(1)
    if not prefix.is_global:
        print("Attempting to use a non-global prefix. Are you sure?")
    

    #for some reason fe80::%IFACE shows up via ndp for a client on my system
    #not sure why but we can't use that one
    ndp = subprocess.run(['/usr/sbin/ndp', '-na'], capture_output=True, text=True)
    for line in ndp.stdout.split("\n"):
       if macaddr in line and "fe80::" in line and not "fe80::%" in line:
           print(line)
           link = re.match('^(.+)%([\S]+)',line)
           try:
               linkaddr = ipaddress.IPv6Address(link[1])
               #TODO check if interface name is valid
               interface = link[2]
           except TypeError:
               print("invalid link address - no match")
               continue
           except ValueError:
               print("invalid link address - ip conversion error")
               continue
           print("Adding route with prefix: " + str(prefix) + " to " + str(linkaddr) + "%" + str(interface))
           subprocess.run(['/sbin/route', '-6', 'add', str(prefix), str(linkaddr)+"%"+str(interface)], capture_output=True, text=True)
           sys.exit(0)

    print("Didn't find a suitable neighbour for routing this prefix")
    sys.exit(1)
