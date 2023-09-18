<?php

require_once 'Logger.php';

class EresseaRunner {

    static function usage($name, $exit = NULL, $command = NULL) {
        echo <<<USAGE

    Usage: $name [-f <configfile>] [-y] [-l <log level>] [-v <verbosity>] [-g <game-id>] [-t <turn>] <command> [<args> ...]

    <configfile> contains all the game settings. Defaults to config.ini in the script's directoy.

    -l <log level>
    -v <verbosity>
        0: quiet
        1: normal
        2: verbose
        3: debug

    -y
        do not ask for confirmation or user input whenever possible



    These commands are available:

        help
            display help information

    TODO

        install [<server directory>] [<games directory>]
            install the server
        upgrade [<branch>]
            recompile and install from source
        newgame <id> <rules>
            start a new game
        gmtool
            open the game editor
        seed <algorithm>
            seed new players
        write_reports [<faction id> ...]
            write reports for all or some factions
        send [<faction id> ...]
            send reports for all or some factions
        fetch
            fetch orders from mail server
        create_orders
            process all orders in queue
        run
            run a turn
        run_all
            do a complete cycle: fetch mail, create-orders, run, send
        announce subject textfile [attachement ...]
            send a message to all players
        backup
            backup relevant data for this turn


USAGE;

    if ($exit !== NULL) {
        exit($exit);
    }
}

    static function info($configfile, $argv, $pos_args) {
        echo "config: '$configfile'\n";
        foreach ($pos_args as $key => $value) {
            echo "arg[$key]: '$value'\n";
        }

        if (empty($pos_args[1])) {
            Logger::error("missing argument");
            EresseaRunner::usage($argv[0], 1);
        }

    }

    static function install($basedir, $pos_args) {
        $installdir = $pos_args[1] ?? $basedir . '/server';
        $gamedir = $pos_args[1] ?? $basedir;

        echo "install server in $installdir, games in $gamedir\n";
    }
}

function parse_config($configfile) {
    if (file_exists($configfile))
        return parse_ini_file($configfile, true);
    return NULL;
}

$usage = false;
$optind = 1;
$log_level = Logger::WARNING;
$verbosity = 1;
while (isset($argv[$optind])) {
    $arg = $argv[$optind];
    if (in_array($arg, ['-h', '--help'])) {
        $usage = true;
    } elseif ('-c' === $arg) {
        $scriptname = $argv[++$optind];
    } elseif ('-f' === $arg) {
        $configfile = $argv[++$optind];
    } elseif ('-v' === $arg) {
        $verbosity = $argv[++$optind];
    } elseif ('-l' === $arg) {
        $log_level = $argv[++$optind];
    } else {
        break;
    }
    ++$optind;
}
if ($usage) {
    EresseaRunner::usage($scriptname ?? $argv[0], 0);
}

$config = parse_config($configfile);

$basedir = dirname($configfile);

$logfile = $config['runner']['logfile'] ?? "$basedir/log/runner.log" ?? NULL;

$logger = new Logger;
$logger->set_level($log_level);

if ($logfile) {
    if (!file_exists(dirname($logfile)))
        mkdir(dirname($logfile));

    if (touch($logfile) && file_exists($logfile) && is_writable($logfile)) {
        $logger->set_file($logfile);
        $logger->info("set log file to '$logfile', log level '$log_level'");
    } else {
        $logger->error("could not access log file '$logfile'");
    }
}

if ($config) {
    $logger->info("config file '$configfile' read");
    $logger->debug($config);
} else {
    $logger->warning("no config file '$configfile'\n");
}
$logger->debug("parameters:\n");
$logger->debug($argv);

$pos_args = array_slice($argv, $optind);

$command = $pos_args[0] ?? 'help';

Logger::info("command $command");

if ($command == 'help') {
    if (empty($pos_args[0]))
        Logger::warning("missing command");
    EresseaRunner::usage($scriptname, empty($pos_args[0]) ? 1 : NULL, $pos_args[0] ?? NULL);
} elseif ('install' == $command) {
    EresseaRunner::install($basedir, $pos_args);
} else {
    if (empty($configfile) || !file_exists($configfile)) {
        $msg = "Config file not found. Either use the install command or the -c option";
        echo "$msg\n";
        Logger::error($msg);
        exit(1);
    } elseif ('info' == $command) {
        EresseaRunner::info($configfile, $argv, $pos_args);
    } else {
        $msg = "unknown command '$command'";
        echo "$msg\n";
        Logger::error($msg);
        EresseaRunner::usage($scriptname, 1);
    }
}
