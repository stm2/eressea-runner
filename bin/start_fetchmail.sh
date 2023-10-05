#!/bin/bash
set -euo pipefail

function abort() {
    echo "$1"
    exit 1
}

quit=0
once="-d0"
while getopts 1lq o; do
  case "${o}" in
  1) once="-d0" ;;
  l) once="" ;;
  q) quit=1 ;;
  *) echo "unknown option -${OPTARG}" ;;
  esac
done
shift $((OPTIND-1))

if [[ $quit -gt 0 ]]; then
    fetchmail --quit || true
    exit 0
fi

[ -n "${1+x}" ] || abort "missing argument fetchmailrc"
[ -f "$1" ] || abort "fetchmailrc $1 not readable"

myid=$(id -u)
# RUNNING=$(pgrep -u "$myid" -x fetchmail || true)
echo fetchmail "$once" -f "$1"
