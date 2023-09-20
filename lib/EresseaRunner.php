<?php

require_once 'Logger.php';

class EresseaRunner {

    private array $config;
    private int $verbosity = 1;
    public bool $confirm_all = false;


    function usage($name, $short = true, $exit = NULL, $command = NULL) {
        echo <<<EOL

USAGE

    $name [-f <configfile>] [-y] [-l <log level>] [-v <verbosity>] [-g <game-id>] [-t <turn>] <command> [<args> ...]


EOL;

        if (!$short) {
            echo <<<EOL

    <configfile> contains all the game settings. Defaults to config.ini in the script's directoy.

    -l <log level>
    -v <verbosity>
        0: quiet
        1: normal
        2: verbose
        3: debug

    -y
        do not ask for confirmation or user input whenever possible


COMMANDS

    These commands are available:

    help
        display help information

    TODO

    install [<branch>] [<server directory>] [<games directory>]
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

THE CONFIG FILE

    The config file is in JSON format:

    {
      "runner": {
        // absolute path to working directory
        // (default: the directory containing the config file or the directory containing the script
        // all relative paths are relative to base
        "base": "/absolute/path",

        // path to a log file (default: logs/runner.log)
        "logfile": "path/to/file",

        // path to server installation directory
        "serverdir": "path/to/dir",

        // path to games directory
        "gamedir": "path/to/dir",
      },

      "game": [
        { "id": "id1" },
        { "id": "id2" }
      ]
    }


EOL;
        }

        if ($exit !== NULL) {
            exit($exit);
        }
    }

    function info($configfile, $argv, $pos_args) {
        echo "\nworking directory: '" . getcwd() . "'\n";
        echo "config: '$configfile'\n";
        foreach ($pos_args as $key => $value) {
            echo "arg[$key]: '$value'\n";
        }

        if (empty($pos_args[1])) {
            Logger::error("missing argument");
            // $this->usage($argv[0], 1);
        }

        echo "\nbase dir: '" . $this->config['runner']['base'] . "'\n";
        echo "log file: '" . $this->config['runner']['logfile'] ."'\n";
        echo "server: '" . $this->config['runner']['serverdir'] . "'\n";
        echo "games: '" . $this->config['runner']['gamedir'] . "'\n\n";

    }

    public function set_verbosity($value) {
        $this->verbosity = $value;
    }

    private function confirm($prompt) {
        if ($this->confirm_all) {
            if ($this->verbosity > 0)
                echo "$prompt [Y]\n";
            return true;
        }
        $continue = readline("$prompt [Y/n] \n");
        return $continue === false || $continue ==='' || strcasecmp($continue, 'y') === 0;
    }

    private function input($prompt, &$value) {
        if ($this->confirm_all) {
            if ($this->verbosity > 0)
                echo "$prompt [$value]\n";
            return true;
        }
        $input = readline("$prompt [$value] ");
        if (!empty($input))
            $value = $input;
        return $input !== false;
    }

    function exec($cmd, $msg = null, $exitonfailure = true) {
        if ($this->verbosity > 0)
            echo "executing '$cmd'...";
        Logger::debug($cmd);

        exec($cmd, $out, $result);

        if ($this->verbosity > 0)
            echo "done\n";

        Logger::debug($out);

        if ($result != 0) {
            Logger::error(empty($msg)?"$cmd exited abnormally.\n":$msg);
            if ($exitonfailure)
                exit(2);
        }
    }

    function install($pos_args, $update = false) {
        $basedir = $this->config['runner']['base'];


        $branch = $pos_args[1] ?? 'master';
        if (!$this->input("install branch: ", $branch))
            exit(0);
        if ($branch !== 'master')
            $this->confirm("Installing versions other than the master branch is unsafe. Continue?");

        $installdir = $pos_args[2] ?? $basedir . '/server';
        // if (!$this->input("install server in ", $installdir))
        //     exit(0);
        $gamedir = $pos_args[3] ?? $basedir;
        // if (!$this->input("install games in ", $gamedir))
        //     exit(0);

        if (!str_starts_with($installdir, "/"))
            $installdir = $basedir . "/" . $installdir;
        if (!str_starts_with($gamedir, "/"))
            $gamedir = $basedir . "/" . $gamedir;

        if (!$this->confirm("install branch $branch of server in $installdir, games in $gamedir?"))
            exit(0);

        if (!$update && file_exists($installdir)) {
            $msg = "installation directory exits, please use the update command";
            if ($this->verbosity > 0)
                echo "$msg\n";
            Logger::info($msg);
            exit(0);
        }

        // doesn not work!
        // $this->exec("cd $basedir/eressea-source");
        chdir("$basedir/eressea-source");

        $this->exec("git fetch");

        $this->exec("git checkout $branch", "Failed to update source. Do you have local changes?");

        $this->exec("git pull --rebase origin $branch", "Failed to update source. Do you have local changes?");

        $this->exec("git submodule update --init --recursive");

        $this->exec("s/cmake-init");

        $this->exec("s/build");

        $this->exec("s/runtests");

        $this->exec("s/install -f");

        $this->config['runner']['serverdir'] = $installdir;
        $this->config['runner']['gamedir'] = $gamedir;
        $this->save_config();
    }

    private function sd(&$c, $path, $i, $default) {
       $step = $path[$i];
        if(!isset($c[$step])) {
            $c[$step] = ($i+1 == count($path)) ? $default : [];
        }
        if ($i+1 < count($path))
            $this->sd($c[$step], $path, $i+1, $default);
    }

    private function set_default(&$config, $path, $default) {
        $this->sd($config, $path, 0, $default);
    }

    function parse_config($configfile) {
        $config = NULL;
        if (file_exists($configfile) && is_readable($configfile)) {
            $raw = file_get_contents($configfile);
            if (empty($raw)) {
                Logger::error("could not read config file '$configfile'");
            } else {
                $config = json_decode($raw, true);
                if ($config == null)
                    Logger::error("invalid config file '$configfile'");
            }
        } else {
            Logger::error("config file '$configfile' not found");
        }

        if (empty($config))
            $config = [];

        $this->set_default($config, ['configfile'], $configfile);
        $this->set_default($config, ['runner', 'base'], dirname($configfile));
        $config['runner']['base'] = realpath($config['runner']['base']);
        $this->set_default($config, ['runner', 'logfile'], 'log/runner.log');
        $this->set_default($config, ['runner', 'serverdir'], 'server');
        $this->set_default($config, ['runner', 'gamedir'], '.');

        $this->config = $config;
        return $config;
    }

    function save_config() {
        $configfile = $this->config['configfile'];
        echo "save $configfile\n";
        if (empty($configfile) || (file_exists($configfile) && !is_writable($configfile))) {
            Logger::error("cannot write to config file '$configfile");
        } else {
            $copy = $this->config;
            unset($copy['configfile']);
            file_put_contents($configfile, json_encode($copy, JSON_PRETTY_PRINT), LOCK_EX);
            Logger::debug("wrote config file $configfile");
        }
    }
}

function get_logger($config, $log_level) {
    if (!isset($config['runner']))
        $logfile = NULL;
    else {
        $logfile = $config['runner']['logfile'] ?? ($config['runner']['base'] . "/log/runner.log") ?? NULL;
        if (!str_starts_with($logfile, "/"))
            $logfile = $config['runner']['base'] . "/" . $logfile;
    }

    $logger = new Logger;
    $logger->set_level($log_level);

    if ($logfile) {
        if (!file_exists(dirname($logfile)))
            mkdir(dirname($logfile));
        if ((file_exists($logfile) && is_writable($logfile)) || is_writable(dirname($logfile))) {
            $logger->set_file($logfile);
            $logger->info("set log file to '$logfile', log level '$log_level'");
        } else {
            $logger->error("could not access log file '$logfile'");
        }
    }
    return $logger;
}

$runner = new EresseaRunner;

$usage = 0;
$optind = 1;
$log_level = Logger::WARNING;
$verbosity = 1;
$scriptname = $_SERVER['PHP_SELF'];

if (realpath($scriptname) !== false)
    $configfile = dirname(dirname(realpath($scriptname))) . "/config.json";
else
    $configfile = realpath(".") . "/config.json";
while (isset($argv[$optind])) {
    $arg = $argv[$optind];
    if (in_array($arg, ['-h', '--help'])) {
        $usage = 2;
    } elseif ('-c' === $arg) {
        $scriptname = $argv[++$optind];
    } elseif ('-f' === $arg) {
        $configfile = $argv[++$optind];
    } elseif ('-v' === $arg) {
        $verbosity = $argv[++$optind];
    } elseif ('-l' === $arg) {
        $log_level = $argv[++$optind];
    } elseif ('-y' === $arg) {
        $runner->confirm_all = true;
    } else if (str_starts_with($arg, "-")) {
        if ($verbosity > 0)
            echo "unknown option '$arg'\n";
        $usage = 1;
    } else {
        break;
    }
    ++$optind;
}
if ($usage) {
    $runner->usage($scriptname ?? $argv[0], true, 0);
}

$runner->set_verbosity($verbosity);

$logger = get_logger(null, $log_level);
$config = $runner->parse_config($configfile);
$logger = get_logger($config, $log_level);

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

$logger->info("command $command");

if ('help' == $command) {
    if (empty($pos_args[0]))
        $logger->warning("missing command");
    $runner->usage($scriptname, false, empty($pos_args[0]) ? 1 : NULL, $pos_args[0] ?? NULL);
} elseif ('install' == $command) {
    $runner->install($pos_args);
} elseif ('update' == $command) {
    $runner->install($pos_args, true);
} else {
    if (empty($configfile) || !file_exists($configfile)) {
        $msg = "Config file '$configfile' not found. Either use the install command or the -c option";
        $logger->error($msg);
        if ($verbosity > 0) {
            echo "$msg\n";
            $runner->usage($scriptname, true, 1);
        }
        exit(1);
    } elseif ('info' == $command) {
        $runner->info($configfile, $argv, $pos_args, $config);
    } else {
        $msg = "unknown command '$command'";
        if ($verbosity > 0)
            echo "$msg\n";
        $logger->error($msg);
        $runner->usage($scriptname, true, 1);
    }
}
