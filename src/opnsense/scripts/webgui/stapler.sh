#!/bin/sh

# ocsp response stapling and refresh
# intended for opnsense webgui (lighttpd) ocsp stapling
# recommended to run this scripty every 5 min to keep stapled response fresh
#

# defaults
freq=5                                 # when checking the OCSP validity, the default is a 5-minute update interval
verb_l=1                               # only errors in stdout
openssl="openssl"                      # <- opnsense uses /usr/local/bin/openssl
tag="lighttpd"                         # send to webgui log. configd log can catch stderr on error
good_only="1"                          # staple only if response good
validate="1"                           # verify OCSP signature by default. disable for debugging only
always_update=""                       # dont fetch too often by default
cert_pem=""                            # cert pem path
ocsp_bin=""                            # ocsp staple der path
issuer_pem=""                          # issuer chain pem path
min_freq=24                            # update ocsp once a day even if its sill valid with nextUpdate present
min_freq2=60                           # update ocsp once a hour if its "always fresh" (no nextUpdate present)

usage() {
    echo "Request and staple OCSP response for server certificate"
    echo
    echo "Options:"
    echo "  -c, --cert file            : server certificate file path"
    echo "  -i, --issuer file          : issuer chain file path"
    echo "  -b, --staple_bin file      : OCSP staple binary file path"
    echo "  -s, --ssl_path file        : open_ssl path"
    echo "  -m, --min_freq hours       : minimal update frequency for OCSP with Next Update time present"
    echo "                             : update response even if Next Update is still valid (default is 24H)"
    echo "  -m2, --min_freq2 minutes   : minimal update frequency for OCSP with no Next Update time present"
    echo "                             : update response based on thisUpdate value and current time with selected frequency"
    echo "  -f, --freq minutes         : this script execution frequency. to check if refresh is needed"
    echo "                             : at this cycle"
    echo "  -d, --debug number         : verbosity level: 0 - quiet, 1 - stdout, 2 - as 1 with logger"
    echo "                             : 3 - as 2 with stderr, 4 - as 3 with debug messages"
    echo "  -t, --tag string           : syslog tag. using lighttpd by default"
    echo "  -ng, --no_good             : staple response even if status is not 'good'. debugging only"
    echo "  -nv, --no_validate         : staple response even if OCSP signature validation failed. debugging only"
    echo "  -a, --always_update        : refresh staple on each run (refresh can be skipped if possible by default)"
    echo "  -h, --help                 : read this"
}

# args processing

while [ $# -gt 0 ]
do

    case "$1" in

        -c|--cert)
            if [ $# -le 1 ]; then
                echo "path value is missing for $1 argument"
                exit 1
            fi
            cert_pem=$2
            shift
            ;;

        -i|--issuer)
            if [ $# -le 1 ]; then
                echo "path value is missing for $1 argument"
                exit 1
            fi
            issuer_pem=$2
            shift
            ;;

        -b|--staple_bin)
            if [ $# -le 1 ]; then
                echo "path value is missing for $1 argument"
                exit 1
            fi
            ocsp_bin=$2
            shift
            ;;

        -s|--ssl_path)
            if [ $# -le 1 ]; then
                echo "path value is missing for $1 argument"
                exit 1
            fi
            openssl=$2
            shift
            ;;

        -m|--min_freq)
            if [ $# -le 1 ]; then
                echo "time value is missing for $1 argument"
                exit 1
            fi
            min_freq=$2
            shift
            ;;

        -m2|--min_freq2)
            if [ $# -le 1 ]; then
                echo "time value is missing for $1 argument"
                exit 1
            fi
            min_freq2=$2
            shift
            ;;

        -d|--debug)
            if [ $# -le 1 ]; then
                echo "debug level value is missing for $1 argument"
                exit 1
            fi
            verb_l=$2
            shift
            ;;

        -t|--tag)
            if [ $# -le 1 ]; then
                echo "syslog tag value is missing for $1 argument"
                exit 1
            fi
            tag=$2
            shift
            ;;

        -f|--freq)
            if [ $# -le 1 ]; then
                echo "time value is missing for $1 argument"
                exit 1
            fi
            freq=$2
            shift
            ;;

        -ng|--no_good)
            good_only=""
            ;;

        -nv|--no_validate)
            validate=""
            ;;

        -a|--always-update)
            always_update="1"
            ;;

        -h|--help)
            usage
            exit 0
            ;;

        *)
            if [ -n "$1" ]; then
                echo "unknown option: $1" >&2
                exit 1
            fi
    esac
    shift
done

say() {
    verb="$1"
    msg="$2"
    prio="user.$3"
    echostderr=0
    if [ "$verb" -le "$verb_l" ]; then
        if [ "$verb_l" -ge 2 ] && [ "$3" != "debug" ] || [ "$verb_l" -ge 4 ]; then
            logger -t $tag -p "$prio" "$msg"
        fi
        if [ "$verb_l" -ge 3 ] && [ "$3" = "error" ]; then
            echostderr=1
        fi
        if [ "$verb_l" -ge 1 ]; then
            [ $echostderr -eq 1 ] && echo "$msg" >&2 || echo "$msg" >&2
        fi
    fi
}

bye() {
    returncode="$1"
    verb="$2"
    msg="$3"
    prio="$4"

    say "$verb" "$msg" "$prio"
    [ -n "$ocsp_tmp" ] && rm -f "$ocsp_tmp"
    exit "$returncode"
}

handle_bad_status() {
    reason="$1"
    if [ "$reason" = "nogood" ]; then
        if [ -z "$good_only" ]; then
            say 3 "ocsp staple: WARNING. Response status is no good but --no_good option is set. Try to staple response. Please dont use in production environment" debug
        else
            bye 1 1 "ocsp staple error: OCSP response status is no good" error
        fi
    elif [ "$reason" = "verify" ]; then
        if [ -z "$validate" ]; then
            say 3 "ocsp staple: WARNING. OCSP signature validation failed but --no_validate option is set. Try to staple response. Please dont use in production environment" debug
        else
            bye 1 1 "ocsp staple error: OCSP signature validation failed" error
        fi
    fi
}

now=$(date +%s)
ocsp_tmp="$ocsp_bin.temp"

# try to keep staple fresh (based on thisUpdate, nextUpdate and current times). time skew tolerance is 5min in openssl - let it be here too.
# FF wants the staple to be no older than a day - let it be here too
# no need to fetch if staple exists, it will be valid on next freq (so and nextUpdate exists) and less then 24H old. or its less then 1H from last update if nextUpdate absents
if [ -z "$always_update" ] && [ -L "$ocsp_bin" ] && [ -e "$ocsp_bin" ] && [ "$freq" -gt 0 ]; then
    target_nextupdate=0
    t=$(readlink "$ocsp_bin")
    # epoch timestamps
    run_freq_sec=$((freq * 60))                     # script run frequency
    min_freq_sec=$((min_freq * 3600))               # min refresh frequency for OCSPs with nextUpdate time
    min_freq2_sec=$((min_freq2 * 60))               # min refresh frequency for OCSPs without nextUpdate time
    target_updated="$(echo "$t" | cut -d '.' -f 3)"  # last refresh time
    target_date="$(echo "$t" | cut -d '.' -f 4)"     # thisUpdate
    target_nextupdate=${t##*.}                       # nextUpdate
    say 4 "ocsp staple: checking if refresh required. now: $now; nextUpdate: $target_nextupdate; thisUpdate: $target_date; last refresh: $target_updated" debug
    if [ -n "$target_updated" ] && [ $((target_updated + min_freq_sec)) -lt $((now + run_freq_sec)) ]; then
        say 4 "ocsp staple: stapled respone is more then ""$min_freq""H old or will be on next cycle. need to renew" debug
    else
        if [ "$target_nextupdate" -gt 0 ] && [ $((now + run_freq_sec)) -lt "$target_nextupdate" ]; then
            bye 0 3 "ocsp staple: ocsp response will be valid over next $freq minutes. skippig request this time" debug
        elif [ "$target_nextupdate" = 0 ] && [ $((now + run_freq_sec)) -lt $((target_date + min_freq2_sec)) ]; then
            bye 0 3 "ocsp staple: ocsp response have no nextUpdate field and updated less then $min_freq2 min. skippig request this time" debug
        else
            if [ "$target_nextupdate" = 0 ] && [ $((target_updated + min_freq2_sec)) -gt "$now" ] && [ $((target_date + min_freq_sec)) -lt "$now" ]; then
                say 4 "ocsp staple: stapled respone have no nextUpdate value and is more then ""$min_freq""H old. some clients may treat this as an outdated response" debug
            fi
            say 4 "ocsp staple: there is less then ""$freq""m before OCSP response expires or it will be more then ""$min_freq2""m since last update for ocsp without nextUpdate. need to renew" debug
        fi
    fi
fi


# read ocsp responder uri from pem
if resp_uri=$(openssl x509 -in "$cert_pem" -ocsp_uri -noout); then
    [ -n "$resp_uri" ] && say 4 "ocsp staple: OCSP Responder URI: $resp_uri" debug
else
    bye 1 1 "ocsp staple error: OCSP Responder URI not found" error
fi

uri_type=$(echo "$resp_uri" | cut -c 1-4)
if [ "$uri_type" != "http" ]; then
    bye 1 1 "ocsp staple error: OCSP Respinder URI format not supported. URI: $resp_uri" error
fi

say 4 "ocsp staple: fetch OCSP. Responder URI: $resp_uri" debug

# set host header
o_ver=$($openssl version | cut -c 9-13)
# o_ver="1.0.1" # <- debug
[ "${o_ver}" != "${o_ver#1.0.}" ] && key_val_sign=" " || key_val_sign="="
ocsp_hh="-header host${key_val_sign}$(echo "$resp_uri" | cut -d/ -f3)"

# fetch OCSP
if resp=$(openssl ocsp -issuer "$issuer_pem" -cert "$cert_pem" -respout "$ocsp_tmp" -noverify -no_nonce -url "$resp_uri" ${ocsp_hh}); then
    say 4 "ocsp staple: OCSP response received. $resp" debug
else
    bye 1 1 "ocsp staple error: OCSP request failed with return code $?. $resp" error
fi

# check response status
resp_status="$(printf %s "$resp" | head -1)"
[ "$resp_status" = "$cert_pem: good" ] || handle_bad_status "nogood"

# parse OCSP dates
next_date="$(echo "$resp" | grep 'Next Update:' | cut -d ' ' -f 3-)"
[ -n "$next_date" ] || next_date=0
this_date="$(echo "$resp" | grep 'This Update:' | cut -d ' ' -f 3-)"
resp_expire=$(date -d "$next_date" +%s 2>/dev/null || date -jf "%b %e %T %Y %Z" "$next_date" +%s)  # nextUpdate in epoch
resp_date=$(date -d "$this_date" +%s 2>/dev/null || date -jf "%b %e %T %Y %Z" "$this_date" +%s)    # thisUpdate in epoch

# validate OCSP signatures
resp_verify=$(openssl ocsp -issuer "$issuer_pem" -verify_other "$issuer_pem" -cert "$cert_pem" -respin "$ocsp_tmp" -no_nonce -out /dev/null 2>&1)
[ "$resp_verify" = "Response verify OK" ] || handle_bad_status "verify"

# save respone and update link.
ocsp_out="$ocsp_bin.$now.$resp_date.$resp_expire"

mv "$ocsp_tmp" "$ocsp_out" || bye 1 1 "ocsp staple error: error saving ocsp response to file $ocsp_out" error
say 4 "ocsp staple: ocsp response saved in $ocsp_out" debug

ln -sf "$ocsp_out" "$ocsp_bin" || bye 1 1 "ocsp staple error: error linking $ocsp_out to $ocsp_bin" error
for i in "$ocsp_bin".*.*.*; do
    created="$(echo "$i" | cut -d '.' -f 3)"
    if [ -n "$created" ] && [ "$created" -ne "$now" ]; then
        rm -f "$i"
    fi
done
say 4 "ocsp staple: ocsp response updated. current time: $now; ocsp update time: $resp_date; ocsp expire time: $resp_expire" debug
