#!/bin/bash
# usage: accept-orders <subject> < mail.txt
set -euo pipefail

subject="$1"

[ -z "${ERESSEA+x}" ] && exit 1
[ -z "$subject" ] && exit 1

pattern="\s*\([A-Za-z].*\)\s\s*\([0-9A-Za-z][0-9A-Za-z]*\)\s\s*\(\(befehl.*\)\|\(order.*\)\)"
[ -n "$(echo "$subject" | sed -e "/$pattern/Id")" ] && exit 1

for ((i=1;i<=3;++i)); do
    parts[$i]=$(echo "$subject" | sed -e "s/$pattern/\\$i/I")
done


lang=en
if [ -n "$(echo "${parts[3]}" | sed -e "/orders.*/Id")" ]; then
    lang=de
fi
gamename=${parts[1]}
suffix=${parts[3]}
game=${parts[2]}
gamedir="$ERESSEA/game-$game"

logfile=$ERESSEA/log/accept-orders.log
echo "$(date) '$gamename' '$game' '$suffix' '$lang'" >> "$logfile"


# trick mutt into finding etc/.muttrc
HOME="$ERESSEA/etc"

[ -d "$gamedir" ] || exit 1
cd "$gamedir"
mkdir -p orders.dir
cd orders.dir
eval "$("$ERESSEA/server/bin/accept-orders.py" "$game" "$lang" | tee -a "$logfile")"
if [ -e "$ACCEPT_FILE" ]
then
    filename=$(basename "$ACCEPT_FILE")
    email="$ACCEPT_MAIL"
    if [ -d "$ERESSEA/orders-php" ]
    then
      php "$ERESSEA/orders-php/cli.php" insert "$filename" "$lang" "$email"
    fi
fi