#!/usr/local/bin/php
<?php

/*
 * Copyright (C) 2018 Robin Schneider <ypid@riseup.net>
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

echo "\nFingerprints of this host follow. Please compare them when connecting to this host to prevent MITM attacks.\n";

echo "\nFingerprints of SSH host keys:\n";

foreach (glob("/conf/sshd/ssh_host_*_key.pub") as $ssh_host_pub_key_file_path) {
    passthru("ssh-keygen -l -f " . escapeshellarg($ssh_host_pub_key_file_path));
}

echo "\nFingerprints of HTTPS X.509 certificate:\n";

passthru("openssl x509 -in /var/etc/cert.pem -noout -fingerprint -sha256");
passthru("openssl x509 -in /var/etc/cert.pem -noout -fingerprint -sha1");
