<?php

/*
 * Copyright (C) 2026 Deciso B.V.
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

namespace OPNsense\Interfaces\FieldTypes;

use OPNsense\Base\FieldTypes\TextField;
use OPNsense\Base\Validators\CallbackValidator;

class Dhcp4SendOptionsField extends TextField
{
    /**
     * All known options from dhclient
     * https://github.com/opnsense/src/blob/10516aac41659315e7081ebdd34b24ec2b9dc953/sbin/dhclient/tables.c#L67
     */
    static $validoptions = [
        'subnet-mask' => 'I',
        'time-offset' => 'l',
        'routers' => 'IA',
        'time-servers' => 'IA',
        'ien116-name-servers' => 'IA',
        'domain-name-servers' => 'IA',
        'log-servers' => 'IA',
        'cookie-servers' => 'IA',
        'lpr-servers' => 'IA',
        'impress-servers' => 'IA',
        'resource-location-servers' => 'IA',
        'host-name' => 't',
        'boot-size' => 'S',
        'merit-dump' => 't',
        'domain-name' => 't',
        'swap-server' => 'I',
        'root-path' => 't',
        'extensions-path' => 't',
        'ip-forwarding' => 'f',
        'non-local-source-routing' => 'f',
        'policy-filter' => 'IIA',
        'max-dgram-reassembly' => 'S',
        'default-ip-ttl' => 'B',
        'path-mtu-aging-timeout' => 'L',
        'path-mtu-plateau-table' => 'SA',
        'interface-mtu' => 'S',
        'all-subnets-local' => 'f',
        'broadcast-address' => 'I',
        'perform-mask-discovery' => 'f',
        'mask-supplier' => 'f',
        'router-discovery' => 'f',
        'router-solicitation-address' => 'I',
        'static-routes' => 'IIA',
        'trailer-encapsulation' => 'f',
        'arp-cache-timeout' => 'L',
        'ieee802-3-encapsulation' => 'f',
        'default-tcp-ttl' => 'B',
        'tcp-keepalive-interval' => 'L',
        'tcp-keepalive-garbage' => 'f',
        'nis-domain' => 't',
        'nis-servers' => 'IA',
        'ntp-servers' => 'IA',
        'vendor-encapsulated-options' => 'X',
        'netbios-name-servers' => 'IA',
        'netbios-dd-server' => 'IA',
        'netbios-node-type' => 'B',
        'netbios-scope' => 't',
        'font-servers' => 'IA',
        'x-display-manager' => 'IA',
        'dhcp-requested-address' => 'I',
        'dhcp-lease-time' => 'L',
        'dhcp-option-overload' => 'B',
        'dhcp-message-type' => 'B',
        'dhcp-server-identifier' => 'I',
        'dhcp-parameter-request-list' => 'BA',
        'dhcp-message' => 't',
        'dhcp-max-message-size' => 'S',
        'dhcp-renewal-time' => 'L',
        'dhcp-rebinding-time' => 'L',
        'dhcp-class-identifier' => 't',
        'dhcp-client-identifier' => 'X',
        'option-62' => 'X',
        'option-63' => 'X',
        'nisplus-domain' => 't',
        'nisplus-servers' => 'IA',
        'tftp-server-name' => 't',
        'bootfile-name' => 't',
        'mobile-ip-home-agent' => 'IA',
        'smtp-server' => 'IA',
        'pop-server' => 'IA',
        'nntp-server' => 'IA',
        'www-server' => 'IA',
        'finger-server' => 'IA',
        'irc-server' => 'IA',
        'streettalk-server' => 'IA',
        'streettalk-directory-assistance-server' => 'IA',
        'user-class' => 't',
        'option-78' => 'X',
        'option-79' => 'X',
        'option-80' => 'X',
        'option-81' => 'X',
        'option-82' => 'X',
        'option-83' => 'X',
        'option-84' => 'X',
        'nds-servers' => 'IA',
        'nds-tree-name' => 'X',
        'nds-context' => 'X',
        'option-88' => 'X',
        'option-89' => 'X',
        'option-90' => 'X',
        'option-91' => 'X',
        'option-92' => 'X',
        'option-93' => 'X',
        'option-94' => 'X',
        'option-95' => 'X',
        'option-96' => 'X',
        'option-97' => 'X',
        'option-98' => 'X',
        'option-99' => 'X',
        'option-100' => 'X',
        'option-101' => 'X',
        'option-102' => 'X',
        'option-103' => 'X',
        'option-104' => 'X',
        'option-105' => 'X',
        'option-106' => 'X',
        'option-107' => 'X',
        'option-108' => 'X',
        'option-109' => 'X',
        'option-110' => 'X',
        'option-111' => 'X',
        'option-112' => 'X',
        'option-113' => 'X',
        'url' => 't',
        'option-115' => 'X',
        'option-116' => 'X',
        'option-117' => 'X',
        'option-118' => 'X',
        'domain-search' => 't',
        'option-120' => 'X',
        'classless-routes' => 'BA',
        'option-122' => 'X',
        'option-123' => 'X',
        'option-124' => 'X',
        'option-125' => 'X',
        'option-126' => 'X',
        'option-127' => 'X',
        'option-128' => 'X',
        'option-129' => 'X',
        'option-130' => 'X',
        'option-131' => 'X',
        'option-132' => 'X',
        'option-133' => 'X',
        'option-134' => 'X',
        'option-135' => 'X',
        'option-136' => 'X',
        'option-137' => 'X',
        'option-138' => 'X',
        'option-139' => 'X',
        'option-140' => 'X',
        'option-141' => 'X',
        'option-142' => 'X',
        'option-143' => 'X',
        'option-144' => 'X',
        'option-145' => 'X',
        'option-146' => 'X',
        'option-147' => 'X',
        'option-148' => 'X',
        'option-149' => 'X',
        'option-150' => 'X',
        'option-151' => 'X',
        'option-152' => 'X',
        'option-153' => 'X',
        'option-154' => 'X',
        'option-155' => 'X',
        'option-156' => 'X',
        'option-157' => 'X',
        'option-158' => 'X',
        'option-159' => 'X',
        'option-160' => 'X',
        'option-161' => 'X',
        'option-162' => 'X',
        'option-163' => 'X',
        'option-164' => 'X',
        'option-165' => 'X',
        'option-166' => 'X',
        'option-167' => 'X',
        'option-168' => 'X',
        'option-169' => 'X',
        'option-170' => 'X',
        'option-171' => 'X',
        'option-172' => 'X',
        'option-173' => 'X',
        'option-174' => 'X',
        'option-175' => 'X',
        'option-176' => 'X',
        'option-177' => 'X',
        'option-178' => 'X',
        'option-179' => 'X',
        'option-180' => 'X',
        'option-181' => 'X',
        'option-182' => 'X',
        'option-183' => 'X',
        'option-184' => 'X',
        'option-185' => 'X',
        'option-186' => 'X',
        'option-187' => 'X',
        'option-188' => 'X',
        'option-189' => 'X',
        'option-190' => 'X',
        'option-191' => 'X',
        'option-192' => 'X',
        'option-193' => 'X',
        'option-194' => 'X',
        'option-195' => 'X',
        'option-196' => 'X',
        'option-197' => 'X',
        'option-198' => 'X',
        'option-199' => 'X',
        'option-200' => 'X',
        'option-201' => 'X',
        'option-202' => 'X',
        'option-203' => 'X',
        'option-204' => 'X',
        'option-205' => 'X',
        'option-206' => 'X',
        'option-207' => 'X',
        'option-208' => 'X',
        'option-209' => 'X',
        'option-210' => 'X',
        'option-211' => 'X',
        'option-212' => 'X',
        'option-213' => 'X',
        'option-214' => 'X',
        'option-215' => 'X',
        'option-216' => 'X',
        'option-217' => 'X',
        'option-218' => 'X',
        'option-219' => 'X',
        'option-220' => 'X',
        'option-221' => 'X',
        'option-222' => 'X',
        'option-223' => 'X',
        'option-224' => 'X',
        'option-225' => 'X',
        'option-226' => 'X',
        'option-227' => 'X',
        'option-228' => 'X',
        'option-229' => 'X',
        'option-230' => 'X',
        'option-231' => 'X',
        'option-232' => 'X',
        'option-233' => 'X',
        'option-234' => 'X',
        'option-235' => 'X',
        'option-236' => 'X',
        'option-237' => 'X',
        'option-238' => 'X',
        'option-239' => 'X',
        'option-240' => 'X',
        'option-241' => 'X',
        'option-242' => 'X',
        'option-243' => 'X',
        'option-244' => 'X',
        'option-245' => 'X',
        'option-246' => 'X',
        'option-247' => 'X',
        'option-248' => 'X',
        'option-249' => 'X',
        'option-250' => 'X',
        'option-251' => 'X',
        'option-252' => 'X',
        'option-253' => 'X',
        'option-254' => 'X',
    ];


    public function getValidators()
    {
        $validators = parent::getValidators();
        $validators[] = new CallbackValidator(["callback" => function ($data) {
            $options = self::$validoptions;
            $messages = [];
            if (empty($data)) {
                return $messages;
            }
            foreach (explode("\n", $data) as $line) {
                $parts = explode(" ", $line, 2);
                if (!empty($parts)) {
                    /* very basic input validation, ignoring the actual types specified in the option list */
                    $payload = trim($parts[1] ?? '');
                    $match_regex = '/^[a-zA-Z0-9 .,:\_+\-\\\\]*$/D';
                    if (str_starts_with($payload, '"') && str_ends_with($payload, '"')) {
                        $payload = substr($payload, 1, -1); /* only single quote wrapping is allowed  (str)*/
                    }
                    if (!isset($options[$parts[0]]) || !preg_match($match_regex, $payload)) {
                        $messages[] = sprintf(
                            gettext('Invalid option or parameters "%s".'),
                            $line
                        );
                    }
                }
            }
            return $messages;
        }
        ]);

        return $validators;
    }
}
