## Additional prerequisites

In order to receive orders and send reports to your players, you need a working e-mail address. Details of getting and configuring this is somewhat beyond the scope of this guide, but we will try to give you some hints. For these purposes you will need additional software. We are going to use fetchmail and procmail to retrieve and filter orders, and mutt to send reports:

```
sudo apt-get install -y sendmail procmail fetchmail mutt libsasl2-modules zip
```

## Security considerations

If you run an actual server, it is highly recommended that you create an extra Linux user account for running and installing the server, instead of using your own user account. That way your personal data is more protected against possible malicious attacks. We also highly recommend getting a separate e-mail address for running the game. This will reduce spam and clogging of your personal mailbox as well as preventing others from accidentally getting access to your private e-mails.

From here on, we will assume that your Linux user name is "eressea" and your e-mail address is "eressea@arda.test".

## Configuring mutt for sending mails

#### Scenario 1: Your own domain

Having a complete server with your own domain name (arda.test for our example) and static IP address is somewhat of a best case. In this case, sending a mail would be as simple as typing `echo "Mail body" | mutt -s "Subjectline" -- recipient@example.com`.
You may skip the muttrc configuration below, although you might just decide to use it anyway.
#### Scenario 2: Public E-Mail Service

You might also use an e-mail service provider like gmail, gmx, mailbox.org, or posteo.de. For using this, you will need to tell the email software mutt how to access your mailbox. Create a file .muttrc your home directory with the following content (modify it to reflect your own data, at least your user name and password as well as your provider's smtp and imap servers):
**TODO** create in the directory $ERESSEA/server/etc; mutt is currently called in send-zip-report, accept-orders.py, sendreport.sh, and s/preview

```ini
# your user name for sending mails (may or may not be your email address)
set my_smtp_user="eressea@arda.test"
# your passphrase
set smtp_pass="use!a!strong!passphrase!"
# your provider's SMTP server
set my_smtp_server="mail.arda.test"
# server's SMTP port, sometimes 587
set my_smtp_port=465

# your user name for getting mails; change if different from smtp user
set imap_user=$my_smtp_user
# your passphrase
set imap_pass=$smtp_pass
# your imap server
set my_imap_server="imap.arda.test"
set my_imap_port=993
# server imap address including port
set folder="imaps://$my_imap_server:$my_imap_port/"
set spoolfile=+INBOX
#set spoolfile="imaps://$my_imap_server@imap.zoho.eu/"

# set to your game server name (optional)
set from = "My Server <$imap_user>"
set use_from = yes

set record=+Sent
#unset record

set smtp_url=smtps://$my_smtp_user:$smtp_pass@$my_smtp_server:$smtp_port

set postponed="Drafts"
# activate TLS if available on the server
set ssl_starttls=yes
# always use SSL when connecting to a server
set ssl_force_tls=yes
# Don't wait to enter mailbox manually 
unset imap_passive        
# Automatically poll subscribed mailboxes for new mail (new in 1.5.11)
#set imap_check_subscribed
# Reduce polling frequency to a sane level
set mail_check=60
# And poll the current mailbox more often (not needed with IDLE in post 1.5.11)
set timeout=10
# keep a cache of headers for faster loading (1.5.9+?)
#set header_cache=~/.hcache
# Display download progress every 5K
#set net_inc=5
```

Check that sending email works by sending yourself a message from the command line:
~~~
echo "Hello from Arda" | mutt -F ~/eressea/server/etc/muttrc  -s "testing" youprivateaddress@example.com
~~~
**TODO**
```
Sending failed for email/report: wochenbericht-1.txt/
Warning: Fcc to an IMAP mailbox is not supported in batch mode
Skipping Fcc to imaps://imap.mailbox.org:993/Gesendet
```
## Additional server configuration

I recommend you try to avoid naming your game Eressea, as that name is already associated with the original Eressea games, and the name of the game system itself. Let's call our game "Arda".

We need to set this and our email address by editing the file `game-1/eressea.ini`. Open this file in a text editor, find the `[game]` section and add these lines (change the values to match your email and game name):

```ini
email = eressea@arda.test
name = Arda
```

You may also add a custom name for your "From" address:

```ini
sender = Arda Server
```

There are a lot of other moving pieces to the game that you could configure at this point, but we will defer this to the section [[Changing the Rules]] below.

## Sending reports

Remember how we created the initial zip reports in [[Starting a Game#Creating initial player reports]]? We have a bunch of report files waiting to be sent to our players. For this, run the follwing command (**BEWARE: This will actually send emails to the addresses you defined earlier!**):

TODO need $ERESSEA

```
$ERESSEA/server/bin/sendreports.sh 1
```

If you followed the example verbatim, this should produce some error messages like `Recipient address rejected: Domain example.com does not accept mail`, because addresses like foo@example.com cannot receive E-Mails. This is an expected error at this point. But in a real game this should send the zipped reports (and additional statistics file called "wochenbericht-0.txt") to all your players.

## Configuring fetchmail and procmail for getting mails

We will use fetchmail to retrieve your mails. Create a file `server/etc/fetchmailrc` with the following content:

```ini
# poll server every 150s
set daemon 150

# insert your provider's pop3 or imap server, user name and password

poll imap.arda.test protocol imap with service 993:
     user "eressea@arda.test" password "use!a!strong!passphrase!" with:
     ssl and sslproto "auto" and keep and:
     mda "/usr/bin/procmail -m ERESSEA=$ERESSEA $ERESSEA/server/etc/procmailrc "
```

Adjust the server name, user name, and password to your data.

`fetchmail` will use `procmail` to find order mails and store them in the game directory. Therefore, you should create a file `server/etc/procmailrc` with the following content:
```
PATH=/usr/local/bin:/usr/bin:/bin

# set to game.mailcmd or game.name from eressea.ini
GAME_NAME=Arda
GAME_ID=1

MAILDIR=$ERESSEA/server/Mail      # you'd better make sure it exists
DEFAULT=$MAILDIR/mbox/   # completely optional
LOGFILE=$MAILDIR/from   # recommended


# $ERESSEA
:0
* $^Subject:.*${GAME_NAME} +${GAME_ID} +BEF.*
| grep -v '>From' | $ERESSEA/server/bin/orders-accept $GAME_ID de

# $ERESSEA
:0
* $^Subject:.*${GAME_NAME} +${GAME_ID} +ORDERS.*
| grep -v '>From' | $ERESSEA/server/bin/orders-accept $GAME_ID en
```

Change the GAME_NAME variable to "Arda" and change the game id, if necessary. This will filter every mail with a subject like "Arda 1 BEFEHLE" and pass them to the orders-accept script. The orders-accept script processes the message bodies and creates individual files turn-... in the `game-1/orders.dir` directory.

Finally, make sure that only you (the user eressea) can read the files with your passwords in it; fetchmail requires this.

```shell
# only user can access the passwords
chmod 700 muttrc fetchmailrc procmailrc
# used by procmail to store mail that is not caught by one of the rules
# e.g. mails with wrong subject line
mkdir $ERESSEA/server/Mail
```

You can now poll the mails from the server and process them:

```shell
fetchmail -f ~/eressea-server/server/etc/fetchmailrc
```

This will start fetchmail in "daemon" mode, retrieving your mails regularly, as defined in the fetchmailrc file. To restart fetchmail after your server reboots, you can put it into your crontab, which is explained later.

**!!ERROR** fails if python2 is not available

## Checking orders and sending feedback to players

TODO

TODO mutt in accept-orders.py

TODO eressea.ini:
[game]
dbname = eressea.db

## Sending report requests

TODO (Reportnachforderung)

## Creating the order file from mail bodies

There is one last step remaining before we can run the turn: We have to parse the mail bodies in `orders.dir` and create an order file `game-1/orders.X` (where X is the current turn number). This is done by the `create-orders` script.

```shell
$ERESSEA/server/bin/create-orders 1 0
```

**ERRROR** Could not open input file: ../../orders-php/cli.php

Similar to the run-turn script we saw in [[Starting a Game#Processing orders]] earlier, this command takes two parameters: the game number and the turn you would like to process. It processes all the mail bodies in `orders.dir` and dumps them into the file `orders.0`. It also renames the `orders.dir` directory to `orders.dir.0`. Note that this somehow freezes the orders: Orders sent in after this point go to orders.dir again, but additional calls of create-orders will refuse to process them if oders.dir.0 exists. This is useful if something goes wrong during order processing: You can fix the errors, then run the server again (by calling run-turn) with the exact same orders as before.

Congratulations if you have come this far! You can now run a server for your friends by repeatedly (replacing X by the turn number) running

```shell
create-orders 1 X
run-turn 1 X
sendreports.sh 1
```

But this would be somewhat tedious, wouldn't it? We can do one better by automatically performing those steps and additionally making sure that fetchmail is always running.

## Read on

* [[Automation with Crontab]]
* [[Using the Web Interface]]
