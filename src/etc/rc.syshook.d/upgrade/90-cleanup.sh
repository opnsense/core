#!/bin/sh

# run a cleanup beforehand to avoid later pkg-upgrade failures
opnsense-update -Fs
