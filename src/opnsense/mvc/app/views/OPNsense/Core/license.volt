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

<style>
    .license-page { max-width: 1040px; padding-bottom: 24px; }
    .license-page .license-hero { border-left: 4px solid #d94f2b; background: #fff; padding: 20px 24px; margin-bottom: 18px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
    .license-page .license-hero h2 { margin: 0 0 6px; font-size: 24px; font-weight: 500; }
    .license-page .license-hero .copyright { color: #666; margin: 0; }
    .license-page .license-card { background: #fff; border: 1px solid #ddd; border-radius: 3px; margin-bottom: 16px; overflow: hidden; }
    .license-page .license-card-header { background: #f7f7f7; border-bottom: 1px solid #ddd; padding: 11px 16px; font-size: 15px; font-weight: 600; }
    .license-page .license-card-header i { color: #d94f2b; width: 22px; }
    .license-page .license-card-body { padding: 16px 20px; line-height: 1.65; }
    .license-page .license-card-body > :last-child { margin-bottom: 0; }
    .license-page .license-conditions { padding-left: 24px; margin: 14px 0; }
    .license-page .license-conditions li { padding: 4px 0 4px 6px; }
    .license-page .license-disclaimer { background: #f5f5f5; border-left: 3px solid #aaa; color: #555; padding: 13px 15px; margin-top: 16px; font-size: 12px; line-height: 1.55; }
    .license-page .project-row { display: flex; align-items: flex-start; padding: 12px 0; border-bottom: 1px solid #eee; }
    .license-page .project-row:first-child { padding-top: 0; }
    .license-page .project-row:last-child { border-bottom: 0; padding-bottom: 0; }
    .license-page .project-name { min-width: 115px; font-weight: 600; }
    .license-page .project-detail { color: #555; }
    @media (max-width: 767px) {
        .license-page { padding-right: 0; }
        .license-page .license-hero { padding: 16px; }
        .license-page .project-row { display: block; }
        .license-page .project-name { margin-bottom: 4px; }
    }
</style>

<section class="col-xs-12 license-page">
    <div class="license-hero">
        <h2>
            <a href="{{ product_website }}" target="_blank" rel="noopener noreferrer">
                {{ product_name }}{% if product_name == 'OPNsense' %}&reg;{% endif %}
            </a>
        </h2>
        <p class="copyright">
            {{ lang._('Copyright') }} &copy; {{ product_copyright_years }} {{ product_copyright_owner }}
            &middot; {{ lang._('All rights reserved.') }}
        </p>
    </div>

    <div class="license-card">
        <div class="license-card-header"><i class="fa fa-balance-scale"></i> {{ lang._('BSD 2-Clause License') }}</div>
        <div class="license-card-body">
            <p>{{ lang._('Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:') }}</p>
            <ol class="license-conditions">
                <li>{{ lang._('Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.') }}</li>
                <li>{{ lang._('Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the distribution.') }}</li>
            </ol>
            <div class="license-disclaimer">
                {{ lang._('THIS SOFTWARE IS PROVIDED "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.') }}
                {{ lang._('IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.') }}
            </div>
        </div>
    </div>

    <div class="license-card">
        <div class="license-card-header"><i class="fa fa-code-fork"></i> {{ lang._('Open source heritage') }}</div>
        <div class="license-card-body">
            {% if product_name != 'OPNsense' %}
                <div class="project-row">
                    <div class="project-name"><a href="https://opnsense.org/" target="_blank" rel="noopener noreferrer">OPNsense&reg;</a></div>
                    <div class="project-detail">{{ product_name }} {{ lang._('is based on') }} OPNsense&reg;, {{ lang._('copyright') }} &copy; Deciso B.V. {{ lang._('All rights reserved.') }}</div>
                </div>
            {% endif %}
            <div class="project-row">
                <div class="project-name"><a href="https://www.freebsd.org/" target="_blank" rel="noopener noreferrer">FreeBSD</a></div>
                <div class="project-detail">OPNsense {{ lang._('is based on') }} FreeBSD, {{ lang._('copyright') }} &copy; {{ lang._('The FreeBSD Project.') }} {{ lang._('All rights reserved.') }}</div>
            </div>
            <div class="project-row">
                <div class="project-name"><a href="https://www.pfsense.org/" target="_blank" rel="noopener noreferrer">pfSense&reg;</a></div>
                <div class="project-detail">OPNsense {{ lang._('is a fork of') }} pfSense&reg; ({{ lang._('Copyright') }} &copy; 2004-2014 Electric Sheep Fencing, LLC. {{ lang._('All rights reserved') }}).</div>
            </div>
            <div class="project-row">
                <div class="project-name"><a href="https://m0n0.ch/wall/" target="_blank" rel="noopener noreferrer">m0n0wall&reg;</a></div>
                <div class="project-detail">pfSense&reg; {{ lang._('is a fork of') }} m0n0wall&reg; ({{ lang._('Copyright') }} &copy; 2002-2013 Manuel Kasper).</div>
            </div>
        </div>
    </div>

    <div class="license-card">
        <div class="license-card-header"><i class="fa fa-cubes"></i> {{ lang._('Third-party software & acknowledgements') }}</div>
        <div class="license-card-body">
            <p>
                {{ lang._('OPNsense includes various freely available software packages and ports.') }}
                <a href="/ui/core/firmware#packages">{{ lang._('The incorporated third party tools are listed here.') }}</a>
            </p>
            <p><i class="fa fa-heart text-danger"></i> {{ lang._('The authors of OPNsense would like to thank all contributors for their efforts.') }}</p>
        </div>
    </div>
</section>
