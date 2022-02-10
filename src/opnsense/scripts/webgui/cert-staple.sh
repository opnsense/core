#!/bin/sh

CERT_PEM="$1"   # input (cert.pem)
CHAIN_PEM="$2"  # input (chain.pem)
OCSP_DER="$3"   # output (staple.der)
OCSP_TMP=""     # temporary file

if [ -z "$CERT_PEM" ] || [ -z "$CHAIN_PEM" ] || [ -z "$OCSP_DER" ] \
   || [ ! -f "$CERT_PEM" ] || [ ! -f "$CHAIN_PEM" ]; then
    echo 1>&2 "usage: cert-staple.sh cert.pem chain.pem staple.der"
    exit 1
fi

errexit() {
    [ -n "$OCSP_TMP" ] && rm -f "$OCSP_TMP"
    exit 1
}

# get URI of OCSP responder from certificate
OCSP_URI=$(openssl x509 -in "$CERT_PEM" -ocsp_uri -noout)
[ $? = 0 ] && [ -n "$OCSP_URI" ] || errexit

OCSP_HOST=
OCSP_HOST=$(echo "$OCSP_URI" | cut -d/ -f3)

# get OCSP response from OCSP responder
OCSP_TMP="$OCSP_DER.$$"
OCSP_RESP=$(openssl ocsp -issuer "$CHAIN_PEM" -cert "$CERT_PEM" -respout "$OCSP_TMP" -noverify -no_nonce -url "$OCSP_URI" -header Host"=$OCSP_HOST")
[ $? = 0 ] || errexit

# parse OCSP response from OCSP responder
ocsp_status="$(printf %s "$OCSP_RESP" | head -1)"
[ "$ocsp_status" = "$CERT_PEM: good" ] || errexit

# validate OCSP response
ocsp_verify=$(openssl ocsp -issuer "$CHAIN_PEM" -verify_other "$CHAIN_PEM" -cert "$CERT_PEM" -respin "$OCSP_TMP" -no_nonce -out /dev/null 2>&1)
[ "$ocsp_verify" = "Response verify OK" ] || errexit

# rename
OCSP_OUT="$OCSP_DER"
mv "$OCSP_TMP" "$OCSP_OUT" || errexit
