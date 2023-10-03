#!/bin/bash
set -euo pipefail

function abort() {
    echo "$1"
    exit 1
}

[ -n "${1+x}" ] || abort "missing argument fetchmailrc"
[ -f "$1" ] || abort "fetchmailrc $1 not readable"

myid=$(id -u)
RUNNING=$(pgrep -u "$myid" -x fetchmail || true)
if [ -z "$RUNNING" ]; then
    echo "fetchmail does not appear to be running. Calling it once."
    fetchmail -f "$1"
else
    echo fetchmail
fi
