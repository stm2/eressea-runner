We now know how to receive orders, process them, actually run the server and send reports to our player. But in order to run a game with a regular schedule (also called ZAT in Eressea lingo), we would like to do all of this regularly. This, of course, requires a regular server that is always on, or at least at every ZAT. There is no technological reason to do this with your computer at home, but you probably want to do this with a virtual server from a hosting provider, a Raspberry Pi, or potentially a server at work or university, although official policies might disagree!

## Automated configuration

If your server is permanently connected to the internet, you can configure it to send order receipts, answer report requests and automagically evaluate turns at given times. You do this using the `cron` service. This program is designed to evaluate certain commands at configurable points in time.

~~~shell
crontab -e
~~~~

This will open an editor that edits your user's `crontab`, the table that tells cron what to do when. You can replace the contents with the following:

```ini
# Crontab for Eressea server
# Your regular environment variables are not visible by cron.
# Adjust these values according to your system.
# Setting variables like this might not work for your crontab implementation.
# If this is the case, you could start a custom bash script here and set
# variables there instead

# there is no notion of environment variables in this kind of file,
# so we can't use $HOME here
ERESSEA=/home/eressea/eressea
FETCHMAILRC=/home/eressea/eressea/server/etc/fetchmailrc

# m h  dom mon dow   command
# starts the fetchmail daemon; note that only one daemon process is permitted per user
00 *  * * *         [ -f $FETCHMAILRC ] && fetchmail --quit -f $FETCHMAILRC >> $ERESSEA/server/fetchmail.log 2>&1

# run the server for game 1 at 9:15 pm every Saturday
# add one line for every game you might be running
15 21 * * Sat        env ERESSEA=$ERESSEA $ERESSEA/server/bin/run-eressea.cron 1

# run order confirmation for game 1 every 5 minutes
*/5 * * * *          $ERESSEA/server/bin/orders.cron 1
```

This example makes sure that the `fetchmail` daemon is running, starts the `orders.cron` script every 5 minutes to check for orders that have arrived and send confirmation emails, and finally runs the eressea server at 9:15 pm every Saturday.

All you have to do may be to adjust the `ERESSEA` and `FETCHMAILRC` variables according to your system, set the game number corresponding to your game in the lines containing `run-eressea.cron` and `orders.cron`, and adjust the time where you want to run the turn. Refer to the crontab manual for an explanation of the timing options.

Should you run multiple games, you would add one `run-eressea` line and one `orders.cron` line for each of them.

You could try running the `orders.cron` and `run-eressea.cron` scripts manually if you want. *Warning* this will actually send reports to your players!

```shell
# run order checker, send feedback
$ERESSEA/server/bin/orders.cron 1

# run the turn
$ERESSEA/server/bin/run-eressea.cron 1
```

**ERROR** backup produces a bunch of errors:
```
/home/dummy/foo/server/bin/backup-eressea: line 3: /home/dummy/foo/server/bin/boxcli.sh: No such file or directory
creating missing backup directory for game 43.
backup turn 0, game 43, files: data/0.dat parteien.full parteien orders.0
tar: parteien.full: Cannot stat: No such file or directory
tar: parteien: Cannot stat: No such file or directory
tar: Exiting with failure status due to previous errors
/home/dummy/foo/server/bin/backup-eressea: line 11: box-upload: command not found
running turn 0, game 43
0 Factions with 1 NMR
ERROR: current turn -1 is before first 0
/home/dummy/foo/server/bin/backup-eressea: line 3: /home/dummy/foo/server/bin/boxcli.sh: No such file or directory
backup reports 0, game 43
tar: eressea.db: Cannot stat: No such file or directory
tar: Exiting with failure status due to previous errors
...
```

That's it, you are good to go! You might want to create your first actual game now. We have summarized all the necessary steps in the [[Quick Server Setup]] document.

## Read on
* [[Quick Server Setup]]
* [[World Creation and Seeding]]
* [[Changing the Rules]]
