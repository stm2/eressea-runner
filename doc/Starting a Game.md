Every game of Eressea needs a data file that contains the map data, player positions and complete state of the world. You can choose to run a single game world, or have multiple worlds side-by-side if you want to operate more than one game.

The game data and all files for a world will reside in a per-game directory. Let's create it now:

```shell
cd ~/eressea/git
s/setup -g 1 -r e2
cd ../game-1
```

This tells the setup tool that we want to set the game "id" to 1, since this is the first (and so far only) game we want to host, and use the E2 rules. You can chose any positive integer as id that is not too big. Each game is defined by a set of rules, and there are currently two rulesets in the distribution, e2 and e3, which describe the rules for the original Eressea, or the E3 and Deveron game, respectively.

What a game also needs is players. To seed new players, you can use a file called `newfactions`. Let's create two example players:

```shell
echo "spieler1@example.com elf de" >> newfactions
echo "player2@example.com human en" >> newfactions
```

The entries, separated by one or more white space characters, are email, race, and language. The language is either "de" for German or "en" for English. You can add one player per line. 

This file is read automatically when the game editor starts. To do this now type the following commands at the prompt to start the editor:

```shell
./eressea
```

This will give you an interactive prompt with a cursor prefixed by `E>`. You can type lua commands here. Type the following commands at the prompt to start the editor:

```shell
require 'config'
gmtool.editor()
```

### Editing the map

Now you see the (empty) map, and a cursor that you can use to navigate with the arrow keys. Notice the coordinates at the bottom left changing when you do so, select some regions with the space bar, and get a feel for how the hex layout of the map is represented in this textual representation.

You can press 'f' to terraform the region under the cursor, or chain ';' and 'f' to terraform all currently selected regions at once, and 'Ctrl + b' creates a block of ocean regions. With just these tools and plenty of patience, you can build yourself a map. Create a map with at least one land region now.

Press S to save your world to a file. Since we are in turn 0, you should call it "0.dat". It will be stored in the `$ERESSEA/game-1/data/` directory, and each future turn will produce a new file. Press 'Q' to exit the editor and Ctrl+D to quit the program and get to the shell prompt.

**!!ERROR!!**: eressea.ini specifies start = 1, but turn (the file) is set to 0!

### Adding Players

At this point, you should have an email address and a choice of playable race for each of your players, and you'll want to seed them on the map so your game can get started. There are several schools of thoughts for the best methods of placement, I personally like putting multiple factions in the same starting hex to encourage cooperation early on, but some GMs prefer to liberally scatter factions across the entire world to encourage exploration first.

Start the editor again:

TODO make a script for this

```shell
./eressea
E> require 'config'
# get_turn() reads the file called turn in the game directory
# it contains the current turn
E> eressea.read_game(get_turn() .. '.dat')
E> gmtool.editor()
```

TODO add 'L' as a short cut to open a data file

By pressing 's' you can now place a faction in the current region. The next time you press 's' places the second faction from the file, and so on. This will also add the typical starting equipment for new factions to their starting unit.

If you walk around the map with the cursor keys, you will see the player's units appear in the region info box on the right hand side of the editor. To find the hexes where players are, chain the keys 'h' and 'p' to highlight all player hexes.

Again, remember to save your world with S after players have been seeded, so you don't lose your work. Leave the editor with 'Q'., and the interactive console with Ctrl-D.

### Creating initial player reports

The next step is to create the initial reports for your players. On the command prompt, type

```shell
./eressea
E> require('config')
E> eressea.read_game('0.dat')
E> init_reports()
E> write_reports()
E> eressea.write_game('0.dat')
```

The last line is important because write_report() resets all the passwords of new factions and we want to save this change to the game data!

**TODO** what is reports.lua (doesn't change password, but also does not create reports.txt)
**!!ERROR:** errno is 2 (No such file or directory) at /home/dummy/foo/eressea-source/src/kernel/db/sqlite.c:281

This should create a bunch of files in the `reports` subdirectory. Open them with a cr viewer like csmapfx or magellan. These are the initial reports for your players. One should be in German, the other one in English. But to send them to the players, we want to pack them neatly into one zip file for each players. To do that, type

```shell
../server/bin/compress.sh 1
```

**TODO** needs ERESSEA
**!!ERROR** missing server/bin/inifile
**!!ERROR** shebang /usr/bin/env python does not work (for compress.sh, sendreport.sh), replace by explicit calls python2 xyz.py instead of ./xyz.py?

This should create a zip file and a .sh bash shell script for every player. That shell script is used to send the email to the players, but we will get to that later.
### Processing orders
In fact, we will skip the whole step of sending reports to players and receiving orders from them for later. Let's instead look at what happens after we have received them. All player orders will land in one file called `orders.1`, where 1 is the turn number. Lets simulate this by just taking the default orders.
```
cd reports
ls *.zip | while read file; do unzip "$file"; done
cat 0-*.txt > ../orders.0
cd ..
```
Now you can run a new turn by typing
```
../server/bin/run-turn 1 0
```
This command takes the game number and the current turn as parameters. It reads the files data/0.dat and orders.0, call the actual eressea server to process the orders, and then writes the new reports to the `reports` directory all in one go, completing the cycle.
### Read on
* [[Setting up E-Mail]]
* [[World Creation and Seeding]]
* [[Automation with Crontab]]
* [[Changing the Rules]]
