Here are the necessary steps to create your own game:

**TODO** update

## Install the server (only once)

See [[Installation]].

```shell
# required software packages
sudo apt-get -y update
sudo apt-get -y install gcc make git cmake liblua5.2-dev libtolua-dev libncurses5-dev libsqlite3-dev libcjson-dev libiniparser-dev libexpat1-dev
# required order processing packages
sudo apt-get install -y php php-sqlite3 python2 zip jq
# required email packages
sudo apt-get install -y sendmail procmail fetchmail mutt libsasl2-modules

echo export ERESSEA=~/eressea >> $HOME/.bashrc
source $HOME/.bashrc

# check out and compile the code
mkdir -p $ERESSEA/server
cd $ERESSEA
git clone https://github.com/eressea/server.git git
cd git
git checkout master
git submodule update --init
s/cmake-init
s/build && s/runtests && s/install

[ -d $HOME/log ] || mkdir $HOME/log
```
**TODO** get rid of log
## Set up E-Mail

* Go to `$ERESSEA/server/etc/` and create `$HOME/.muttrc`, `fetchmailrc` and `procmailrc` as described in [[Setting up E-Mail]].
* TODO configure turn checker

## Start a game (once per game)

See [[Starting a Game]].

```shell
cd $ERESSEA/git
# replace 1 with a game number that you may choose;
# use either e2 or e3 as ruleset according to taste
s/setup -g 1 -r e2
```

## Configure game name and server address

Edit `game-1/eressea.ini`, for example by calling


```shell
# replace 1 with your game number
nano $ERESSEA/game-1/eressea.ini
```

Add the following lines in the [game] section of the file, replacing values with your actual data:

```ini
email = eressea@arda.test
name = Arda
sender = Arda Server
```

## Setup your game rules (once per game, but completely optional)

See [[Changing the Rules]].

## Add new players (once or potentially several times per game)

See [[Starting a Game#Editing the map]], [[The Game Editor]], and [[World Creation and Seeding]].

```shell
# replace 1 with your game number
cd $ERESSEA/game-1

# repeat this step for every new player or create the newfactions file in some other way. For example, using a spread sheet and saving it in csv format using tabs as separators works fine
echo "spieler1@example.com elf de" >> newfactions

# start the game editor
./eressea

# this will give you an E> prompt
E> require 'config'
E> gmtool.editor()

# this will start the map editor
# create your map, use the 's' key to seed the players from your newfactiosn file
# on by one
# press S and type 0.dat to save your world
# press Q, then Ctrl+D to exit
```

See [[Starting a Game#Creating initial player reports]].

```shell
# create reports and prepare for sending
$ERESSEA/game-1/eressea $ERESSEA/server/scripts/write-reports.lua
# 1 is your game number
$ERESSEA/server/bin/compress.sh 1
```

### Add new players to a running game

TODO
Works more or less like the step above.

## Setup backup (technically optional, but highly recommended)

TODO

## Send initial reports (once per game)

Run
```
# replace 1 with your game ID
$ERESSEA/server/sendreports.sh 1
```

## Evaluate a turn by hand (once per turn)
You can skip this step if you choose to [[#Setup automatic turns with crontab (once per game)|Setup automatic turns]] below.

```shell
fetchmail -f $ERESSEA/server/etc/fetchmailrc
# give fetchmail some time to process all the mails
# maybe check with
# fetchmail -q -c -f $ERESSEA/server/etc/fetchmailrc

# replace 1 with your game id
$ERESSEA/server/bin/run-eressea.cron 1

# the former command actually runs the following steps which you could also call one by one:
### create the order file from mails, prevents sending of additional orders for this turn
### replace X with your turn
# create-orders 1 X
### backup
# backup-eressea
### read orders, run turn, create reports
# run-turn 1 X
### one more backup
# backup-eressea
### create zip reports and send scripts
# compress.sh 1 X
# sendreports.sh 1
# backup-eressea
```

## Setup automatic turns with crontab (once per game)
Run `crontab -e` and edit the crontab contents according to [[Automation with Crontab]]. 

## Updating the server code

TODO
