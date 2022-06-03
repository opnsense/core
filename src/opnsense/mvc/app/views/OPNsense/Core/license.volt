{#
 # Copyright (c) 2019 Deciso B.V.
 # All rights reserved.
 #
 # Redistribution and use in source and binary forms, with or without modification,
 # are permitted provided that the following conditions are met:
 #
 # 1. Redistributions of source code must retain the above copyright notice,
 #    this list of conditions and the following disclaimer.
 #
 # 2. Redistributions in binary form must reproduce the above copyright notice,
 #    this list of conditions and the following disclaimer in the documentation
 #    and/or other materials provided with the distribution.
 #
 # THIS SOFTWARE IS PROVIDED “AS IS” AND ANY EXPRESS OR IMPLIED WARRANTIES,
 # INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 # AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 # AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 # OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 # SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 # INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 # CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 # ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 # POSSIBILITY OF SUCH DAMAGE.
 #}

<section class="col-xs-11">
    <p><a href="{{ product_website }}" target="_blank">{{ product_name }}{% if product_name == 'OPNsense' %}®{% endif %}</a> is Copyright &copy; {{ product_copyright_years }} {{ product_copyright_owner }}<br>All rights reserved.</p>
    <p>Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:</p>
    <ol><li>Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.</li>
        <li>Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the distribution.</li></ol>
    <p>THIS SOFTWARE IS PROVIDED "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES,
        INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
        AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL
        THE AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL,
        EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO,
        PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS;
        OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY,
        WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR
        OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF
        ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.</p>
    {% if product_name != 'OPNsense' %}
        <p>{{ product_name }} is based on <a href="https://opnsense.org/" target="_blank">OPNsense&reg;</a>, copyright &copy; Deciso B.V. All rights reserved.</p>
    {% endif %}
    <p>OPNsense is based on <a href="https://www.freebsd.org/" target="_blank">FreeBSD</a>, copyright &copy; The FreeBSD Project. All rights reserved.</p>
    <p>OPNsense is a fork of <a href="http://www.pfsense.org" target="_blank">pfSense&reg;</a> <small>(Copyright &copy; 2004-2014 Electric Sheep Fencing, LLC. All rights reserved.)</small>, which is a fork of <a href="http://m0n0.ch/wall" target="_blank">m0n0wall&reg;</a> <small>(Copyright &copy; 2002-2013 Manuel Kasper)</small>.</p>
    <p>OPNsense includes various freely available software packages and ports.
        The incorporated third party tools are listed <a href="/ui/core/firmware#packages">here</a>.</p>
    <p>The authors of OPNsense would like to thank all contributors for their efforts.</p>
</section>
