#!/bin/bash
# usage: backup.sh <game> <turn>
set -euo pipefail

abort() {
  echo "$1"
  exit 1
}

[ -z "${1+x}" ] && abort "missing game"
[ -z "${2+x}" ] && abort "missing turn"

[ -z "${ERESSEA+x}" ] && abort "ERESSEA environment variable not set"

GAME=$1
TURN=$2

[ -d "$ERESSEA/game-$GAME" ] || abort "no such game $ERESSEA/game-$GAME"
cd "$ERESSEA/game-$GAME" || abort "could not enter $ERESSEA/game-$GAME"

[[ -z "$TURN" && -s turn ]] && TURN=$(cat turn)

[ -e "data/$TURN.dat" ] || abort "no data for turn $TURN in game $GAME"

if [ ! -d "$ERESSEA/backup/game-$GAME" ] ; then
  echo "creating missing backup directory for game $GAME."
  mkdir -p "$ERESSEA/backup/game-$GAME"
  ln -sf "$ERESSEA/backup/game-$GAME" backup
fi


if [ -e reports/reports.txt ] ; then
  echo "backup reports turn $TURN, game $GAME"
  tar cjf "backup/$TURN-reports.tar.bz2" reports
fi

if [ -e "orders.dir.$TURN" ] ; then
  echo "backup raw orders turn $TURN, game $GAME"
  tar cjf "backup/$TURN-orders.tar.bz2" "orders.dir.$TURN"
fi


files=(
turn
data/$TURN.dat
orders.$TURN
eressea.db
passwd
score
parteien
parteien.full
eressea.log
eressea.ini
)

existing=()
for file in "${files[@]}"; do
  [ -e "$file" ] && existing+=("$file")
done

echo "backup turn $TURN, game $GAME, files: ${existing[*]}"
tar --no-auto-compress -cjf "backup/$TURN.tar.bz2" "${existing[@]}"
