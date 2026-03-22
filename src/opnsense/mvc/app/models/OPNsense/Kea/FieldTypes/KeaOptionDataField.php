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

namespace OPNsense\Kea\FieldTypes;

use OPNsense\Base\FieldTypes\BaseField;
use OPNsense\Base\Validators\CallbackValidator;
use OPNsense\Firewall\Util;

// Avoid stringly-typed encoding values: enum guarantees valid types
enum KeaEncoding: string
{
    case HEX = 'hex';
    case IPV4 = 'ipv4-address';
    case IPV6 = 'ipv6-address';
    case UINT8 = 'uint8';
    case UINT16 = 'uint16';
    case UINT32 = 'uint32';
    case INT32 = 'int32';
    case BOOLEAN = 'boolean';
    case STRING = 'string';
    case FQDN = 'fqdn';
}

class KeaOptionDataField extends BaseField
{
    protected $internalIsContainer = false;
    protected $internalValidationMessage = "Invalid option data";

    private $internalCodeSource = 'code';
    private $internalEncodingSource = 'encoding';
    private string $internalOptionSpace = 'dhcp4';

    private const VALIDATOR_MAP = [
        KeaEncoding::HEX->value => 'validateHex',
        KeaEncoding::IPV4->value => 'validateIpv4List',
        KeaEncoding::IPV6->value => 'validateIpv6List',
        KeaEncoding::UINT8->value => 'validateUInt8',
        KeaEncoding::UINT16->value => 'validateUInt16',
        KeaEncoding::UINT32->value => 'validateUInt32',
        KeaEncoding::INT32->value => 'validateInt32',
        KeaEncoding::BOOLEAN->value => 'validateBoolean',
        KeaEncoding::STRING->value => 'validateString',
        KeaEncoding::FQDN->value => 'validateFqdn',
    ];

    private const ENCODER_MAP = [
        KeaEncoding::HEX->value => 'encodeHex',
        KeaEncoding::IPV4->value => 'encodeIpv4',
        KeaEncoding::IPV6->value => 'encodeIpv6',
        KeaEncoding::UINT8->value => 'encodeUInt8',
        KeaEncoding::UINT16->value => 'encodeUInt16',
        KeaEncoding::UINT32->value => 'encodeUInt32',
        KeaEncoding::INT32->value => 'encodeInt32',
        KeaEncoding::BOOLEAN->value => 'encodeBool',
        KeaEncoding::STRING->value => 'encodeString',
        KeaEncoding::FQDN->value => 'encodeFqdn',
    ];

    /**
     * For any complex or unknown DHCP type, user configurable HEX is the bailout right now.
     * Each of these types can have custom validators and encoders added. Complex codes are commented and typed as HEX intentionally.
     * Validation ensures only HEX or the correct encoding type can be chosen for each option code.
     * The main benefit is that we never have to touch config generation again, since all types will be serialized as HEX.
     */

    private const DHCPV4_OPTION_TYPES = [
        1   => [KeaEncoding::IPV4->value],
        2   => [KeaEncoding::INT32->value],
        3   => [KeaEncoding::IPV4->value],
        4   => [KeaEncoding::IPV4->value],
        5   => [KeaEncoding::IPV4->value],
        6   => [KeaEncoding::IPV4->value],
        7   => [KeaEncoding::IPV4->value],
        8   => [KeaEncoding::IPV4->value],
        9   => [KeaEncoding::IPV4->value],
        10  => [KeaEncoding::IPV4->value],
        11  => [KeaEncoding::IPV4->value],
        12  => [KeaEncoding::STRING->value],
        13  => [KeaEncoding::UINT16->value],
        14  => [KeaEncoding::STRING->value],
        15  => [KeaEncoding::FQDN->value],
        16  => [KeaEncoding::IPV4->value],
        17  => [KeaEncoding::STRING->value],
        18  => [KeaEncoding::STRING->value],
        19  => [KeaEncoding::BOOLEAN->value],
        20  => [KeaEncoding::BOOLEAN->value],
        21  => [KeaEncoding::IPV4->value],
        22  => [KeaEncoding::UINT16->value],
        23  => [KeaEncoding::UINT8->value],
        24  => [KeaEncoding::UINT32->value],
        25  => [KeaEncoding::UINT16->value],
        26  => [KeaEncoding::UINT16->value],
        27  => [KeaEncoding::BOOLEAN->value],
        28  => [KeaEncoding::IPV4->value],
        29  => [KeaEncoding::BOOLEAN->value],
        30  => [KeaEncoding::BOOLEAN->value],
        31  => [KeaEncoding::BOOLEAN->value],
        32  => [KeaEncoding::IPV4->value],
        33  => [KeaEncoding::IPV4->value],
        34  => [KeaEncoding::BOOLEAN->value],
        35  => [KeaEncoding::UINT32->value],
        36  => [KeaEncoding::BOOLEAN->value],
        37  => [KeaEncoding::UINT8->value],
        38  => [KeaEncoding::UINT32->value],
        39  => [KeaEncoding::BOOLEAN->value],
        40  => [KeaEncoding::STRING->value],
        41  => [KeaEncoding::IPV4->value],
        42  => [KeaEncoding::IPV4->value],
        43  => [KeaEncoding::HEX->value],       // Vendor-Specific Information: TLV container (enterprise-id + nested options)
        44  => [KeaEncoding::IPV4->value],
        45  => [KeaEncoding::IPV4->value],
        46  => [KeaEncoding::UINT8->value],
        47  => [KeaEncoding::STRING->value],
        48  => [KeaEncoding::IPV4->value],
        49  => [KeaEncoding::IPV4->value],
        50  => [KeaEncoding::IPV4->value],
        51  => [KeaEncoding::UINT32->value],
        52  => [KeaEncoding::UINT8->value],
        53  => [KeaEncoding::UINT8->value],
        54  => [KeaEncoding::IPV4->value],
        55  => [KeaEncoding::UINT8->value],
        56  => [KeaEncoding::STRING->value],
        57  => [KeaEncoding::UINT16->value],
        58  => [KeaEncoding::UINT32->value],
        59  => [KeaEncoding::UINT32->value],
        60  => [KeaEncoding::STRING->value],
        61  => [KeaEncoding::HEX->value],       // Client Identifier: variable binary (DUID or hardware type + address)
        62  => [KeaEncoding::STRING->value],
        63  => [KeaEncoding::HEX->value],       // NetWare/IP domain: structured binary format, not plain string
        64  => [KeaEncoding::STRING->value],
        65  => [KeaEncoding::IPV4->value],
        66  => [KeaEncoding::STRING->value],
        67  => [KeaEncoding::STRING->value],
        68  => [KeaEncoding::IPV4->value],
        69  => [KeaEncoding::IPV4->value],
        70  => [KeaEncoding::IPV4->value],
        71  => [KeaEncoding::IPV4->value],
        72  => [KeaEncoding::IPV4->value],
        73  => [KeaEncoding::IPV4->value],
        74  => [KeaEncoding::IPV4->value],
        75  => [KeaEncoding::IPV4->value],
        76  => [KeaEncoding::IPV4->value],
        77  => [KeaEncoding::HEX->value],       // User-Class: length-prefixed list of opaque values
        78  => [KeaEncoding::HEX->value],       // SLP Directory Agent: complex record (flags + addresses)
        79  => [KeaEncoding::HEX->value],       // SLP Service Scope: structured list with encoding rules
        81  => [KeaEncoding::HEX->value],       // FQDN option: flags + encoded domain (not plain DNS format)
        82  => [KeaEncoding::HEX->value],       // Relay Agent Information: nested suboptions (circuit-id, remote-id)
        85  => [KeaEncoding::IPV4->value],
        86  => [KeaEncoding::STRING->value],
        87  => [KeaEncoding::STRING->value],
        88  => [KeaEncoding::FQDN->value],
        89  => [KeaEncoding::IPV4->value],
        90  => [KeaEncoding::HEX->value],       // Authentication: protocol-specific binary structure
        91  => [KeaEncoding::UINT32->value],
        92  => [KeaEncoding::IPV4->value],
        93  => [KeaEncoding::UINT16->value],
        94  => [KeaEncoding::HEX->value],       // Client NDI: opaque binary identifier
        97  => [KeaEncoding::HEX->value],       // UUID/GUID: fixed 16-byte binary structure
        98  => [KeaEncoding::STRING->value],
        99  => [KeaEncoding::HEX->value],       // GEOCONF: civic location (RFC4776 structured TLV)
        100 => [KeaEncoding::STRING->value],
        101 => [KeaEncoding::STRING->value],
        108 => [KeaEncoding::UINT32->value],
        112 => [KeaEncoding::IPV4->value],
        113 => [KeaEncoding::STRING->value],
        114 => [KeaEncoding::STRING->value],
        116 => [KeaEncoding::UINT8->value],
        117 => [KeaEncoding::UINT16->value],
        118 => [KeaEncoding::IPV4->value],
        119 => [KeaEncoding::FQDN->value],
        124 => [KeaEncoding::HEX->value],       // Vendor-Identifying Vendor Class: enterprise + TLV list
        125 => [KeaEncoding::UINT32->value],
        136 => [KeaEncoding::IPV4->value],
        137 => [KeaEncoding::FQDN->value],
        138 => [KeaEncoding::IPV4->value],
        141 => [KeaEncoding::FQDN->value],
        146 => [KeaEncoding::HEX->value],       // ANDSF: complex policy structure (3GPP)
        159 => [KeaEncoding::HEX->value],       // DHCP Captive Portal (binary URL encoding variant)
        212 => [KeaEncoding::HEX->value],       // 6RD: IPv6 + IPv4 + prefix structure (mixed types)
        213 => [KeaEncoding::FQDN->value],
    ];

    private const DHCPV6_OPTION_TYPES = [
        1   => [KeaEncoding::HEX->value],       // client-id (DUID structure, variable binary)
        2   => [KeaEncoding::HEX->value],       // server-id (DUID structure, variable binary)
        3   => [KeaEncoding::HEX->value],       // ia-na (container with nested options)
        4   => [KeaEncoding::HEX->value],       // ia-ta (deprecated container option)
        5   => [KeaEncoding::HEX->value],       // iaaddr (nested address structure with lifetimes)
        6   => [KeaEncoding::UINT16->value],
        7   => [KeaEncoding::UINT8->value],
        8   => [KeaEncoding::UINT16->value],
        9   => [KeaEncoding::HEX->value],       // relay-msg (encapsulated DHCPv6 message)
        11  => [KeaEncoding::HEX->value],       // auth (complex authentication structure)
        12  => [KeaEncoding::IPV6->value],
        13  => [KeaEncoding::UINT16->value],
        14  => [KeaEncoding::HEX->value],       // rapid-commit (empty flag option, no payload)
        15  => [KeaEncoding::HEX->value],       // user-class (opaque binary list of identifiers)
        16  => [KeaEncoding::HEX->value],       // vendor-class (vendor-specific binary format)
        17  => [KeaEncoding::HEX->value],       // vendor-opts (nested vendor option container)
        18  => [KeaEncoding::HEX->value],       // interface-id (relay-provided opaque identifier)
        21  => [KeaEncoding::FQDN->value],
        22  => [KeaEncoding::IPV6->value],
        23  => [KeaEncoding::IPV6->value],
        24  => [KeaEncoding::FQDN->value],
        25  => [KeaEncoding::HEX->value],       // ia-pd (prefix delegation container)
        26  => [KeaEncoding::HEX->value],       // iaprefix (nested prefix structure)
        27  => [KeaEncoding::IPV6->value],
        28  => [KeaEncoding::IPV6->value],
        29  => [KeaEncoding::FQDN->value],
        30  => [KeaEncoding::FQDN->value],
        31  => [KeaEncoding::IPV6->value],
        32  => [KeaEncoding::UINT32->value],
        33  => [KeaEncoding::FQDN->value],
        34  => [KeaEncoding::IPV6->value],
        36  => [KeaEncoding::HEX->value],       // geoconf-civic (record: uint8, uint16, binary)
        37  => [KeaEncoding::HEX->value],       // remote-id (record: uint32, binary)
        38  => [KeaEncoding::HEX->value],       // subscriber-id (binary blob)
        39  => [KeaEncoding::HEX->value],       // client-fqdn (record with flags + fqdn encoding)
        40  => [KeaEncoding::IPV6->value],
        41  => [KeaEncoding::STRING->value],
        42  => [KeaEncoding::STRING->value],
        43  => [KeaEncoding::UINT16->value],
        44  => [KeaEncoding::HEX->value],       // lq-query (record: uint8, ipv6-address)
        45  => [KeaEncoding::HEX->value],       // client-data (empty container option)
        46  => [KeaEncoding::UINT32->value],
        47  => [KeaEncoding::HEX->value],       // lq-relay-data (record: ipv6-address + binary)
        48  => [KeaEncoding::IPV6->value],
        51  => [KeaEncoding::FQDN->value],
        52  => [KeaEncoding::IPV6->value],
        53  => [KeaEncoding::HEX->value],       // relay-id (binary identifier)
        56  => [KeaEncoding::HEX->value],       // ntp-server (empty container, suboptions)
        57  => [KeaEncoding::FQDN->value],
        58  => [KeaEncoding::FQDN->value],
        59  => [KeaEncoding::STRING->value],
        60  => [KeaEncoding::HEX->value],       // bootfile-param (tuple type, variable structure)
        61  => [KeaEncoding::UINT16->value],
        62  => [KeaEncoding::HEX->value],       // nii (record: uint8, uint8, uint8)
        64  => [KeaEncoding::FQDN->value],
        65  => [KeaEncoding::FQDN->value],
        66  => [KeaEncoding::HEX->value],       // rsoo (empty container option)
        67  => [KeaEncoding::HEX->value],       // pd-exclude (binary prefix exclusion format)
        74  => [KeaEncoding::HEX->value],       // rdnss-selection (record: ipv6, uint8, fqdn)
        79  => [KeaEncoding::HEX->value],       // client-linklayer-addr (binary MAC-like structure)
        80  => [KeaEncoding::IPV6->value],
        82  => [KeaEncoding::UINT32->value],
        83  => [KeaEncoding::UINT32->value],
        88  => [KeaEncoding::IPV6->value],
        89  => [KeaEncoding::HEX->value],       // s46-rule (complex record: mixed v4/v6 fields)
        90  => [KeaEncoding::IPV6->value],
        91  => [KeaEncoding::HEX->value],       // IPV6_PREFIX
        92  => [KeaEncoding::HEX->value],       // s46-v4v6bind (record: ipv4 + ipv6-prefix)
        93  => [KeaEncoding::HEX->value],       // s46-portparams (record: uint8 + psid)
        94  => [KeaEncoding::HEX->value],       // s46-cont-mape (empty container)
        95  => [KeaEncoding::HEX->value],       // s46-cont-mapt (empty container)
        96  => [KeaEncoding::HEX->value],       // s46-cont-lw (empty container)
        103 => [KeaEncoding::STRING->value],
        136 => [KeaEncoding::HEX->value],       // v6-sztp-redirect (tuple type)
        143 => [KeaEncoding::IPV6->value],
        144 => [KeaEncoding::HEX->value],       // v6-dnr (record: uint16, uint16, fqdn, binary)
        148 => [KeaEncoding::HEX->value],       // addr-reg-enable (empty flag option)
    ];

    /* Public endpoints */

    public function setCodeSource($value): void
    {
        if (!empty($value)) {
            $this->internalCodeSource = $value;
        }
    }

    public function setEncodingSource($value): void
    {
        if (!empty($value)) {
            $this->internalEncodingSource = $value;
        }
    }

    public function setOptionSpace($value): void
    {
        if (in_array($value, ['dhcp4', 'dhcp6'], true)) {
            $this->internalOptionSpace = $value;
        }
    }

    public function encodeValue(): string
    {
        $encoding = $this->getEncoding();
        if ($encoding === null) {
            return '';
        }
        $method = self::ENCODER_MAP[$encoding->value] ?? null;
        if ($method ===  null) {
            return '';
        }
        return $this->$method($this->getValue());
    }

    public function getValidators()
    {
        $validators = parent::getValidators();
        if ($this->isSet()) {
            $validators[] = new CallbackValidator([
                "callback" => function ($data) {
                    $encoding = $this->getEncoding();
                    if ($encoding === null || !isset(self::VALIDATOR_MAP[$encoding->value])) {
                        return [gettext("Unsupported encoding type. Use hex for complex options.")];
                    }
                    if (!$this->isEncodingAllowed()) {
                        $code = $this->getParentNode()->{$this->internalCodeSource}->asInt();
                        $allowed = $this->getOptionTypeMap()[$code] ?? null;
                        if ($allowed === null) {
                            // unknown option fallback message
                            return [
                                sprintf(
                                    gettext("Encoding '%s' is not valid for this DHCP option, use hex instead."),
                                    $encoding->value
                                )
                            ];
                        }
                        return [
                            sprintf(
                                gettext("Encoding '%s' is not valid for option %d, use %s or hex."),
                                $encoding->value,
                                $code,
                                implode(', ', $allowed)
                            )
                        ];
                    }
                    $method = self::VALIDATOR_MAP[$encoding->value] ?? null;
                    if ($method === null) {
                        return [gettext("Unsupported encoding type. Use hex for complex options.")];
                    }
                    return $this->$method($data);
                }
            ]);
        }
        return $validators;
    }

    /* Helpers */

    private function getEncoding(): ?KeaEncoding
    {
        return KeaEncoding::tryFrom($this->getParentNode()->{$this->internalEncodingSource}->getValue());
    }

    private function isEncodingAllowed(): bool
    {
        $encoding = $this->getEncoding();
        if ($encoding === null || $encoding === KeaEncoding::HEX) {
            return true; // configuring hex is always allowed as bailout
        }
        $code = $this->getParentNode()->{$this->internalCodeSource}->asInt();
        $map = $this->getOptionTypeMap();
        if (!isset($map[$code])) {
            return false; // unknown options are hex-only
        }
        return in_array($encoding->value, $map[$code], true);
    }

    private function toList(string $data): array
    {
        return array_values(array_filter(
            array_map('trim', explode(',', $data)),
            fn($v) => $v !== ''
        ));
    }

    private function getOptionTypeMap(): array
    {
        return $this->internalOptionSpace === 'dhcp6'
            ? self::DHCPV6_OPTION_TYPES
            : self::DHCPV4_OPTION_TYPES;
    }

    /* Encoders */

    private function encodeHex(string $data): string
    {
        return strtoupper($data);
    }

    /**
     * Convert each IPv4 address into hex:
     * - split into octets (e.g. "192.168.1.1" to [192,168,1,1])
     * - format each octet as 2-digit uppercase hex (C0A80101)
     * - concatenate all addresses without separators
     */
    private function encodeIpv4(string $data): string
    {
        return implode('', array_map(function ($ip) {
            return implode('', array_map(fn($o) => sprintf('%02X', (int)$o), explode('.', $ip)));
        }, $this->toList($data)));
    }

    /**
     * Convert each IPv6 address into hex:
     * - inet_pton() returns packed binary (16 bytes)
     * - bin2hex() returns hex representation
     * - concatenate all addresses without separators
     */
    private function encodeIpv6(string $data): string
    {
        return implode('', array_map(function ($ip) {
            return strtoupper(bin2hex(inet_pton($ip)));
        }, $this->toList($data)));
    }

    private function encodeUInt(string $data, int $bits): string
    {
        return strtoupper(str_pad(dechex((int)$data), (int)($bits / 4), '0', STR_PAD_LEFT));
    }

    private function encodeUInt8(string $data): string
    {
        return $this->encodeUInt($data, 8);
    }

    private function encodeUInt16(string $data): string
    {
        return $this->encodeUInt($data, 16);
    }

    private function encodeUInt32(string $data): string
    {
        return $this->encodeUInt($data, 32);
    }

    private function encodeInt32(string $data): string
    {
        return strtoupper(bin2hex(pack('l', (int)$data)));
    }

    private function encodeBool(string $data): string
    {
        $data = strtolower(trim($data));
        return ($data === 'true' || $data === '1') ? '01' : '00';
    }

    private function encodeString(string $data): string
    {
        return strtoupper(bin2hex($data));
    }

    /**
     * Encode FQDN in DNS wire format:
     * - each label is prefixed with its length (1 byte)
     * - label content is ASCII-encoded as hex
     * - domain is terminated with a zero-length label (00)
     * - "example.com" becomes: "07 6578616D706C65 03 636F6D 00"
     */
    private function encodeFqdn(string $data): string
    {
        $result = '';
        foreach (explode('.', $data) as $label) {
            $result .= sprintf('%02X', strlen($label)) . strtoupper(bin2hex($label));
        }
        return $result . '00';
    }

    /* Validators */

    private function validateHex(string $data): array
    {
        if (!preg_match('/^([0-9A-Fa-f]{2})+$/', $data)) {
            return [gettext("Hex value must contain valid hexadecimal byte pairs.")];
        }
        return [];
    }

    private function validateIpv4List(string $data): array
    {
        $messages = [];
        foreach ($this->toList($data) as $ip) {
            if (!Util::isIpv4Address($ip)) {
                $messages[] = sprintf(gettext("Invalid IPv4 address: %s"), $ip);
            }
        }
        return $messages;
    }

    private function validateIpv6List(string $data): array
    {
        $messages = [];
        foreach ($this->toList($data) as $ip) {
            if (!Util::isIpv6Address($ip)) {
                $messages[] = sprintf(gettext("Invalid IPv6 address: %s"), $ip);
            }
        }
        return $messages;
    }

    private function validateUInt(string $data, int $bits): array
    {
        if (!ctype_digit($data)) {
            return [gettext("Value must be a positive integer.")];
        }
        $max = (2 ** $bits) - 1;
        if ((int)$data > $max) {
            return [sprintf(gettext("Value exceeds %d-bit limit."), $bits)];
        }
        return [];
    }

    private function validateUInt8(string $data): array
    {
        return $this->validateUInt($data, 8);
    }

    private function validateUInt16(string $data): array
    {
        return $this->validateUInt($data, 16);
    }

    private function validateUInt32(string $data): array
    {
        return $this->validateUInt($data, 32);
    }

    private function validateInt32(string $data): array
    {
        if (!preg_match('/^-?\d+$/', $data)) {
            return [gettext("Value must be an integer.")];
        }
        $value = (int)$data;
        if ($value < -2147483648 || $value > 2147483647) {
            return [gettext("Value exceeds int32 range.")];
        }
        return [];
    }

    private function validateBoolean(string $data): array
    {
        $data = strtolower(trim($data));
        if (!in_array($data, ['true', 'false', '0', '1'], true)) {
            return [gettext("Boolean must be true/false or 0/1.")];
        }
        return [];
    }

    private function validateString(string $data): array
    {
        if (preg_match('/[\'"]/', $data)) {
            return [gettext("String must not contain quotes.")];
        }
        return [];
    }

    private function validateFqdn(string $data): array
    {
        if (!preg_match('/^([a-zA-Z0-9-]+\.)*[a-zA-Z0-9-]+$/', $data)) {
            return [gettext("Invalid FQDN.")];
        }
        return [];
    }
}
