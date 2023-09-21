#!/usr/bin/env bash


if ! command -v php &> /dev/null
then
    echo "php could not be found"
    exit 1
fi

SOURCE=${BASH_SOURCE[0]}
while [ -L "$SOURCE" ]; do # resolve $SOURCE until the file is no longer a symlink
  BASE=$( cd -P "$( dirname "$SOURCE" )" >/dev/null 2>&1 && pwd )
  SOURCE=$(readlink "$SOURCE")
  [[ $SOURCE != /* ]] && SOURCE=$BASE/$SOURCE # if $SOURCE was a relative symlink, we need to resolve it relative to the path where the symlink file was located
done
BASE=$( cd -P "$( dirname "$SOURCE" )" >/dev/null 2>&1 && pwd )
PROG=$(basename ${BASH_SOURCE[0]})

php $BASE/../lib/Json2Cr.php -c $PROG "$@"