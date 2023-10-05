<?php

require_once 'Logger.php';

enum StatusCode : int {
    /** do not exit */
    case STATUS_GOOD = -1;
    /** no error */
    const STATUS_NORMAL = 0;
    /** command line error */
    const STATUS_PARAMETER = 1;
    /** unspecified error */
    const STATUS_ERROR = 2;
    /** error during execution */
    const STATUS_EXECUTION = 3;
}


class EresseaRunner {

    private string $scriptname;
    private array $config;
    private ?string $lua_path = NULL;

    private int $verbosity = 1;
    public bool $confirm_all = false;
    public ?string $game_id = null;
    public int $turn = -1;
    public bool $fakemail = false;

    const GAME_ID_PATTERN = "/^[\p{L}\p{N}]+$/";

    function set_scriptname(string $name) : void {
        $this->scriptname = $name;
    }

    const COMMANDS = [
        'help' => [
            'std_runner' => true,
            'commandline' => "help [<command>]",
            'short' =>  'display help information; try help <command> or help config for information about a command or the configuration file'
        ],
        'install' => [
            'commandline' => 'install [ --orders-php | --server | --runner | --mail | --cron ] [<branch>] [--update] [--nopull]',
            'short' => 'install the server'
        ],
        'game' => [
            'std_runner' => true,
            'commandline' => 'game <rules>',
            'short' => 'start a new game'
        ],
        'eressea' => [
            'std_runner' => true,
            'commandline' => "eressea [args]...",
            'short' => 'call the eressea server'
        ],
        'gmtool' => [
            'std_runner' => true,
            'short' => 'open the game editor'
        ],
        'seed' => [
            'std_runner' => true,
            'commandline' => 'seed [-r] [<algorithm>]',
            'short' => 'seed new players'
        ],
        'reports' => [
            'std_runner' => true,
            'commandline' => 'reports [-p]',
            'short' => 'write reports for all factions'
        ],
        'send' => [
            'std_runner' => true,
            'commandline' => 'send [ [<faction id> [<e-mail>] ]...',
            'short' => 'send reports for all or some factions'
        ],
        'fetch' => [
            'std_runner' => true,
            'commandline' => 'fetch [ --once | --listen | --quit ]',
            'short' => 'fetch orders from mail server (once, continuously listen, stop listening) '
        ],
        'create_orders' => [
            'std_runner' => true,
            'short' => 'process all orders in queue'
        ],
        'run' => [
            'std_runner' => true,
            'short' => 'run a turn'
        ],
        'run_all' => [
            'short' => 'do a complete cycle: fetch mail, create-orders, run, send'
        ],
        'announce' => [
            'commandline' => 'announce subject textfile [attachement]...',
            'short' => 'send a message to all players'
        ],
        'backup' => [
            'short' => 'backup relevant data for this turn'
        ]
    ];

    const HELP = [
        0 => <<<EOL
USAGE
    %s [-f <configfile>] [-y] [-l <log level>] [-v <verbosity>]
        [-g <game-id>] [-t <turn>] <command> [<args>]...

    <configfile> contains all the game settings.
        Defaults: /real-path-to-script/../conf/config.php

    -l <log level>
    -v <verbosity>
        0 = quiet, 1 = normal, 2 = verbose, 3 = debug
    -y:
        do not ask for confirmation or user input whenever possible

    Use %s help commands to get a list of all commands
    and %s help <command> for help about a command.


EOL,
        'commands' => <<<EOL
COMMANDS

    These commands are available:

EOL,

        'install' => <<<EOL

    Install the server. <branch> is the repository branch (defaults to master)

    --server
        install only the server itself (the default)
    --orders-php
        install only the orders-php part
    --runner
        install the runner itself
    --mail
        setup e-mail service
    --cron
        setup the cron service; supports additional options:
        --list shows the currently installed crontab
        --edit opens the crontab editor to make changes manually
        --clear clears all eressea crontab entries

    --update
        must be used if the server has already been installed
    --nopull
        do not pull updates from the repository, just install the files

EOL,

        'game' =>
        "You can use -g and -t to set the game ID and start turn, respectively. <rules> is the rule set (e2 or e3).",

        'seed' => <<<EOL
This command reads the newfactions file in the game directory. This file contains one line for each new faction. A line contains an email address, a race, a langugage code (de or en), and optional additonal parameters all separated by one or more white space charaters. Lines starting with # are ignored.

It then uses the given algorithm to create a new map and place the new factions on it. It also creates a file "autoseed.json" in the game directory. You can edit the file to change the behavior of the seeding algorithm.

If you don't specify an algorithm or if the algorithm in the autoseed.json file matches the given algorithm, the file is read and the algorithm from the configuration file is executed with the given parameters.

The generated map is saved to auto.dat in the data directory. If the -r option is given, it the current turn data in 'data/<turn>.dat' is also replaced with the generated map.

EOL,

        'reports' => <<<EOL
Write all report files. With -p it also re-generates all the passwords and writes them back to the report. Note that, if you run it without -p, there will be no new password messages in the reports and no passwords in the turn templates for new factions.

EOL,

        'config' => <<<EOL
THE CONFIG FILE

    The config file is a PHP file:

<?php
    \$ERrunner = array (
        // absolute path to working directory; all relative paths are relative to base
        // default: the parent of the directory containing the config file or the directory
        // containing the script
        'basedir' => '/absolute/path',
        // path to directory containing all the games
        'gamedir' => '.',
        // path to main server installation directory
        'serverdir' => 'server',
        // path to a log file
        'logfile' => 'log/runner.log',
        // mutt configuration file (if used for sending mails)
        'muttrc' => '/home/steffen/.muttrc',
    );
    // game specific configurations
    \$ERgame = array (
    );
?>

EOL,

    ];

    function cmd(string $command, array $pos_args) {
        if (isset(static::COMMANDS[$command])) {
            $cmd="cmd_$command";
            $this->$cmd($pos_args);
        }
    }


    function get_cmd_line(string $command) : string {
        return (EresseaRunner::COMMANDS[$command]['commandline'] ?? $command) . "\n";
    }

    function line_break(string $text, string $prefix = '', $linewidth = 100) : string {
        $info = "";
        $lines = explode("\n", $text);
        foreach($lines as $line) {
            preg_match("/^( *)(.*)/", $line, $matches);
            $indent = $matches[1] . $prefix;
            $line = $matches[2];
            if (!empty($info))
                $info .= "\n";
            $words = explode(" ", $line);
            $info .= $indent;
            $pos = mb_strlen($indent);
            foreach ($words as $word) {
                if ($pos != 0 && $pos + mb_strlen($word) > $linewidth) {
                    $info .= "\n$indent";
                    $pos = mb_strlen($indent)+1;
                } elseif ($pos > mb_strlen($indent)) {
                    $info .= " ";
                    ++$pos;
                }
                $info .= $word;
                $pos+=mb_strlen($word);
            }
        }
        return $info;
    }

    function short_info(string $command) : string {
        $cinfo = EresseaRunner::COMMANDS[$command];
        $cline = $this->get_cmd_line($command);

        return "    $cline" . $this->line_break($cinfo['short'], '        ');
    }

    function usage(bool $short = true, int $exit = NULL, string $command = NULL) : void {
        $name = $this->scriptname;

        if ($command == NULL) {
            echo sprintf(static::HELP[0], $name, $name, $name);
        } elseif ('config_not_found' == $command) {
            echo "    You can create a config file with the install or the create_config command.\n";
            echo "    You can manually set the location of the config file with the -c parameter.\n";
        } else {
            echo "USAGE:\n";
            if ('commands' == $command) {
                echo $this->line_break(static::HELP[$command]);
                foreach(static::COMMANDS as $cmd => $cinfo) {
                    echo $this->short_info($cmd);
                    echo "\n";
                }
            } else if ("eressea" == $command) {
                echo $this->get_cmd_line($command);
                echo "        call the eressea server with the given arguments\n\n";
                $eressea = $this->get_server_directory() . "/bin/eressea";
                if (is_executable($eressea)) {
                    if (chdir(dirname($eressea))) {
                        putenv("PATH=.");
                        passthru("eressea --help");
                    }
                } else
                    echo "Eressea executable no found; did you run '$name install'?\n";
            } elseif (isset(static::COMMANDS[$command])) {
                if (isset(static::HELP[$command])) {
                    echo $this->get_cmd_line($command);
                    echo $this->line_break(static::HELP[$command], "    ");
                } else {
                    echo $this->short_info($command);
                    echo "\n";
                }
            } else if (isset(static::HELP[$command])) {
                echo static::HELP[$command];
            } else {
                echo "    No information on the command '$command'.\n";
            }
            echo "\n";
        }

        if ($exit !== NULL) {
            exit($exit);
        }
    }

    function info(string $msg) : void {
        if ($this->verbosity > 0) {
            echo "$msg\n";
        }
        Logger::info($msg);
    }

    function error(string $msg) : void {
        if ($this->verbosity > 0) {
            echo "$msg\n";
        }
        Logger::error($msg);
    }

    function log(string $msg) : void {
        if ($this->verbosity > 1) {
            echo "$msg\n";
        }
        Logger::info($msg);
    }

    function debug(string $msg) : void {
        if ($this->verbosity > 2) {
            echo "$msg\n";
        }
        Logger::debug($msg);
    }

    function abort(string $msg, int $exitcode) : void {
        if ($exitcode == StatusCode::STATUS_NORMAL) {
            $this->info($msg);
        } else {
            $this->error($msg);
        }

        if ($exitcode != StatusCode::STATUS_GOOD)
            exit($exitcode);
    }

    function set_fakemail(bool $fake) : void {
        $this->fakemail = $fake;
    }

    function cmd_info(string $configfile, array $argv, array $pos_args) : void {
        $this->info("\nworking directory: '" . getcwd() . "'");
        $this->info("config: '$configfile'");
        foreach ($pos_args as $key => $value) {
            $this->log("arg[$key]: '$value'\n");
        }

        $this->info("base dir: '" . ($this->config['runner']['basedir'] ?? "-?-") . "'");
        $this->info("log file: '" . ($this->config['runner']['logfile'] ?? "-?-") ."'");
        $this->info("server: '" . ($this->config['runner']['serverdir'] ?? "-?-") . "'");
        $this->info("games: '" . ($this->config['runner']['gamedir'] ?? "-?-") . "'");
        $this->info("muttrc: '" . ($this->config['runner']['muttrc'] ?? "-?-") . "'");

        if (isset($this->config['runner']['basedir']) &&
            isset($this->config['runner']['gamedir']) &&
            chdir($this->get_game_directory())) {
            $this->info("games:");
            $dirs = glob("game-*");
            foreach($dirs as $dir) {
                if (is_dir($dir) && file_exists($dir . '/eressea.ini')) {
                    $this->info("    $dir");
                }
            }
        }

        if (isset($this->config['runner']['basedir']) &&
            isset($this->config['runner']['serverdir'])) {
            $serverdir = $this->get_server_directory();

            $out = [];
            $this->call_eressea('', [ '--version' ], $out);
            $text = "eressea: ";
            foreach($out as $line)
                $text .= $line;

            $this->info("$text\n");
        }

    }

    public function set_game(string $id) : void {
        if (preg_match(static::GAME_ID_PATTERN, $id) !== 1)
            $this->abort("Game ID should be a positive integer ($id)", StatusCode::STATUS_PARAMETER);

        $this->game_id = $id;
    }

    public function get_current_game() : ?string {
        $gamedir = $this->get_game_directory();
        $currentdir = realpath(".");
        if (dirname($currentdir) != $gamedir) {
            $this->debug("Not in $gamedir");
            return null;
        }
        if (strpos($currentdir, "$gamedir/game-") === 0) {
            $id = substr($currentdir, strlen("$gamedir/game-"));
            if (!preg_match(static::GAME_ID_PATTERN, $id)) {
                $this->debug("Invalid game id $id");
                return null;
            }
            $this->debug("Found game dir $id");
            return $id;
        } else {
            $this->debug("Apparently not in $gamedir/game-id");
        }
        return null;
    }

    public function set_turn(int $turn) : void {
        if (intval($turn) != $turn || intval($turn) < 0) {
            $this->abort("<turn> must be a non-negative integer (got $turn)", StatusCode::STATUS_PARAMETER);
        }
        $this->turn = $turn;
    }

    public function get_current_turn() : int {
        if ($this->turn >= 0)
            return $this->turn;
        $turnfile = $this->get_game_directory($this->game_id) . "/turn";
        if ($this->game_id == null || !file_exists($turnfile))
            $this->abort("Turn not set and no turn file in " . basename(dirname($turnfile)), StatusCode::STATUS_EXECUTION);

        $turn = file_get_contents($turnfile);
        if ($turn === false || intval($turn) != $turn || intval($turn) < 0) {
            $this->abort("Invalid turn file in " . basename(dirname($turnfile)), StatusCode::STATUS_EXECUTION);
        }
        $turn = intval($turn);

        return $turn;
    }

    public function set_verbosity(int $value) {
        $this->verbosity = $value;
    }

    private function confirm(string $prompt, string $default = 'Y') : bool {
        if ($default == 'Y') $hints = 'Y/n'; else $hints = 'y/N';
        if ($this->confirm_all) {
            if ($this->verbosity > 0)
                echo "$prompt [$hints]\n";
            return true;
        }
        $lines = explode("\n", $prompt);
        for($l = 0; $l < count($lines) - 1; ++$l) {
            echo $lines[$l] . "\n";
        }
        $continue = readline($lines[count($lines)-1] . " [$hints] \n");
        if ($continue === false || $continue ==='')
            $continue = $default;
        return strcasecmp($continue, 'y') === 0;
    }

    private function input(string $prompt, ?string &$value) : bool {
        if ($this->confirm_all) {
            if ($this->verbosity > 0)
                echo "$prompt [$value]\n";
            return true;
        }

        $lines = explode("\n", $prompt);
        for($l = 0; $l < count($lines) - 1; ++$l) {
            echo $lines[$l] . "\n";
        }

        $input = readline($lines[count($lines)-1] . " [$value] ");
        if (!empty($input))
            $value = $input;
        return $input !== false;
    }

    function chdir(string $dirname, bool $abort = true) : bool {
        if (!chdir($dirname)) {
            $msg = "Could not enter directory $dirname";
            if ($abort)
                $this->abort($msg, StatusCode::STATUS_EXECUTION);
            else
                $this->error($msg);
            return false;
        }
        return true;
    }


    function exec(string $cmd, string $error_msg = null, bool $exitonfailure = true, string|false &$result = null) : array {
        $this->log("Executing '$cmd'...");

        exec($cmd, $out, $result);

        $this->log("done (exit status $result)");
        foreach($out as $line)
            $this->debug($line);

        if ($result != 0) {
            $this->abort(empty($error_msg)?"$cmd exited abnormally.\n":$error_msg, $exitonfailure?StatusCode::STATUS_EXECUTION:StatusCode::STATUS_GOOD);
        }

        return $out;
    }

    function cmd_install(array $pos_args) : void {
        $basedir = $this->get_base_directory();

        $pull = true;
        $update = false;
        foreach($pos_args as $arg) {
            if ("--mail" == $arg) {
                $this->install_mail();
                return;
            } else if ("--cron" == $arg) {
                array_shift($pos_args);
                $this->install_cron($pos_args);
                return;
            } else if ("--orders-php" == $arg) {
                $this->info("not implemented");
                return;
            } else if ("--runner" == $arg) {
                $this->info("not implemented");
                return;
            } else if ("--update" == $arg) {
                $update = true;
            } else if ("--nopull" == $arg) {
                $pull = false;
            } else if (empty($branch)) {
                $branch = $arg;
            } else {
                $this->abort("unknown parameter $arg", StatusCode::STATUS_PARAMETER);
            }
        }

        // TODO check dependencies git cmake ...?
        if (!empty($this->config['runner']['srcbranch']) && empty($branch))
            $branch = $this->config['runner']['srcbranch'];

        if (empty($branch) && is_dir("$basedir/eressea-source")) {
            $out = $this->exec("git -C '$basedir/eressea-source' branch --show-current");
            if (!empty($out))
                $branch = $out[0];
        }

        if (empty($branch))
            $branch = 'master';

        if (!$this->input("Install branch: ", $branch))
            exit(StatusCode::STATUS_NORMAL);
        if ($branch !== 'master')
            if (!$this->confirm("Installing versions other than the master branch is unsafe. Continue?"))
                exit(StatusCode::STATUS_NORMAL);

        $installdir = $this->config['runner']['serverdir'];
        // if (!$this->input("Install server in ", $installdir))
        //     exit(StatusCode::STATUS_NORMAL);
        $gamedir = $pos_args[2] ?? $this->config['runner']['gamedir'];
        // if (!$this->input("Install games in ", $gamedir))
        //     exit(StatusCode::STATUS_NORMAL);

        if (!str_starts_with($installdir, "/"))
            $abs_installdir = $basedir . "/" . $installdir;
        if (!str_starts_with($gamedir, "/"))
            $abs_gamedir = $basedir . "/" . $gamedir;

        if (!$this->confirm("Install branch $branch of server in $abs_installdir, games in $abs_gamedir?"))
            exit(StatusCode::STATUS_NORMAL);

        if (!$update && file_exists($abs_installdir)) {
            $msg = "Installation directory exists, please use the --update option";
            $this->abort($msg, StatusCode::STATUS_NORMAL);
        }

        $this->info("stopping fetchmail");
        $this->cmd_fetch([ "--quit" ]);

        // does not work:
        // $this->exec("cd $basedir/eressea-source");
        $this->chdir("$basedir/eressea-source");

        if ($pull) {
            $this->exec("git fetch");

            $this->exec("git checkout '$branch'", "Failed to update source. Do you have local changes?");

            $this->exec("git pull --ff-only", "Failed to update source. Do you have local changes?");

            $this->exec("git submodule update --init --recursive");

            $this->config['runner']['srcbranch'] = $branch;
        }

        $this->exec("s/cmake-init");

        $this->exec("s/build");

        $this->exec("s/runtests");

        $this->exec("s/install -f");

        // $this->config['runner']['serverdir'] = $installdir;
        $this->config['runner']['gamedir'] = $gamedir;

        $this->chdir($basedir);
        $this->save_config();

        if ($this->confirm("Would you also like to setup e-mail?")) {
            $this->install_mail();
        }

        if ($this->confirm("Would you also like to setup the cron service?")) {
            $this->install_cron();
        }
    }

    const INI_TEMPLATE = <<<EOF
[game]
locales = de,en
id      = %s
start   = %d

[lua]
install = %s
rules   = %s

EOF;

    const NEWFACTIONS_TEMPLATE = <<<EOF
# this file is read by gmtool
# add one line per faction
# separate columns by one or more spaces (no spaces at the start)
# email race language
# for example
#enno@eressea.test cat de
EOF;

    function cmd_game(array $pos_args) : void{
        $gamedir = $this->get_game_directory();
        $this->chdir($gamedir);

        $game_id = $this->game_id;
        $gamesub = "game-$game_id";
        if ($game_id === null) {
            for($game_id = 0; ; ++$game_id) {
                $gamesub = "game-$game_id";
                if (!file_exists("$gamedir/$gamesub")) {
                    break;
                }
            }
        }
        if (preg_match(static::GAME_ID_PATTERN, $game_id) !== 1)
            $this->abort("Invalid game ID '$game_id'", StatusCode::STATUS_EXECUTION);

        if (file_exists("$gamedir/$gamesub")) {
            $this->abort("Game dir " . realpath("$gamedir/$gamesub") . " already exists", StatusCode::STATUS_EXECUTION);
        }

        $turn = $this->turn;
        if ($turn < 0)
            $turn = 0;

        $rules = "e2";
        if (!empty($pos_args[0]))
            $rules = $pos_args[0];

        if (!preg_match("/^[A-Za-z0-9_.-]*\$/", $rules))
            $this->abort("Invalid ruleset '$rules'", StatusCode::STATUS_PARAMETER);

        $serverdir = $this->get_server_directory();
        $configfile =  "$serverdir/conf/$rules/config.json";
        if (!file_exists($configfile))
            $this->abort("Cannot find config $configfile", StatusCode::STATUS_PARAMETER);


        if (!$this->confirm("Create game with rules $rules in '$gamesub'?"))
            exit(StatusCode::STATUS_NORMAL);

        mkdir($gamesub);
        $this->chdir($gamesub);
        mkdir("data");
        mkdir("reports");

        file_put_contents("eressea.ini",
            sprintf(static::INI_TEMPLATE, $game_id, $turn, $serverdir, $rules));
        file_put_contents("turn", "$turn");
        file_put_contents("newfactions", static::NEWFACTIONS_TEMPLATE);
        symlink("$serverdir/bin/eressea", "eressea");
        symlink("$serverdir/scripts", "scripts");
        symlink("$serverdir/scripts/config.lua", "config.lua");
        symlink("$serverdir/bin", "bin");
    }

    function cmd_eressea(array $pos_args) : void {
        $this->goto_game();

        $this->call_eressea('', $pos_args);
    }

    function check_game() : string {
        if ($this->game_id !== null) {
            $gameid = $this->game_id;
        } else {
            $gameid = $this->get_current_game();
            if ($gameid === null) {
                $this->abort("Game id not set and not in game directory", StatusCode::STATUS_PARAMETER);
            }
            $this->game_id = $gameid;
        }

        $gamedir = $this->get_game_directory($gameid);
        $this->chdir($gamedir);

        return $gameid;
    }

    function goto_game() : bool {
        $gameid = $this->check_game();
        $gamedir = $this->get_game_directory($gameid);
        return $this->chdir($gamedir);
    }

    function cmd_seed(array $pos_args) : void {
        $this->goto_game();

        $pos = 0;
        $replace = false;
        if (isset($pos_args[$pos]) && $pos_args[$pos] == '-r') {
            $replace = true;
            $pos++;
        }

        $algo = $pos_args[$pos] ?? "spiral";

        $configfile = "autoseed.json";
        if (file_exists($configfile)) {
            $config = $this->parse_json($configfile);
            if ($config['algo'] != null && isset($pos_args[0]) && $config['algo'] != $algo) {
                $backup = $this->backup_file($configfile);
                $this->info('Found existing autoseed.json file that does not match ' . $algo .
                    ".\nMoving existing file to $backup.");
                $config = [];
            }
        } else {
            $config = [];
        }


        if ("spiral" == $algo) {
            $config['algo'] = $algo;
            $this->save_json($configfile, $config);
        } else {
            $this->abort("Unknown seeding algorithm $algo", StatusCode::STATUS_PARAMETER);
        }
        $scriptname = "seeding/autoseed.lua";

        $this->call_eressea($scriptname);

        if ($replace) {
            $turn = $this->get_current_turn();
            if ($turn == -1) {
                $this->error("No turn specified.");
            } else {
                $this->info("Copying auto.dat to $turn.dat");
                copy('data/auto.dat', "data/$turn.dat");
            }

        }
    }

    function set_lua_path() :void {
        if ($this->lua_path == null) {
            $lua_path = getenv("LUA_PATH");
            $path = getenv("PATH");
            $this->debug("old lua path $lua_path");
            $this->debug("old path $path");

            $paths = $this->exec("luarocks path");
            foreach($paths as $line) {
                preg_match("/^export ([^=]*=)'(.*)'/", $line, $matches);
                $line = $matches[1] . $matches[2];
                $this->debug($line);
                putenv($line);
            }

            $lua_path = getenv("LUA_PATH");
            $path = getenv("PATH");
            $this->debug("luarocks lua path $lua_path");
            $this->debug("luarocks path $path");

            if ($lua_path === false) $lua_path = '';
            putenv("LUA_PATH=" . $this->get_base_directory() . "/scripts/?.lua;./?.lua;" .
                 $this->get_server_directory() . "/scripts/?.lua;$lua_path");
            exec('echo $LUA_PATH', $out);
            $lua_path = $out[0];
            $this->debug("new lua path $lua_path");
            $this->lua_path = $lua_path;
        }
    }

    function call_eressea(string $script, array $args = [], array &$out = null) : bool {
        $this->set_lua_path();

        if (empty($script)) {
            $scriptname = '';
        } elseif (strpos($script, ".") === 0) {
            $scriptname = $script;
        } else {
            $scriptname = $this->get_base_directory() . "/scripts/$script";
        }
        if (!empty($scriptname))
            $scriptname = escapeshellarg($scriptname);

        $argstring = "";
        foreach($args as $arg) {
            $argstring .= " " . escapeshellarg($arg);
        }

        $eressea = escapeshellarg($this->get_server_directory() . "/bin/eressea");

        $this->log("$eressea $argstring $scriptname\n");
        if ($out !== null){
            $result = false;
            $out = $this->exec("$eressea $argstring $scriptname", null, true, $result);
            return $result;
        } else {
            return passthru("$eressea $argstring $scriptname") === null;
        }
    }

    function call_script(string $script, array $args = []) : bool {
        $this->set_lua_path();

        if (empty($script)) {
            $this->abort("Empty script", StatusCode::STATUS_EXECUTION);
        } elseif (strpos($script, "/") === 0) {
            $this->info("ATTENTION! Calling script with absolute path $script.");
            $scriptname = $script;
        } else {
            $scriptname = $this->get_server_directory() . "/bin/$script";
        }

        if (!is_executable($scriptname)) {
            $this->abort("$script not executable", StatusCode::STATUS_EXECUTION);
        }
        $scriptname = escapeshellarg($scriptname);

        putenv('ERESSEA=' . $this->get_base_directory());

        $argstring = "";

        foreach($args as $arg) {
            $argstring .= " " . escapeshellarg($arg);
        }
        $this->log("$scriptname $argstring\n");

        return passthru("$scriptname $argstring") ===   null;
    }

    function cmd_gmtool(array $pos_args) : void {
        $this->goto_game();

        $this->call_eressea("editor.lua");
    }

    function cmd_reports(array $pos_args) : void {
        $this->goto_game();

        $this->info("deleting old reports");
        $this->exec("rm -rf reports");

        $this->info("writing new files");
        if (isset($pos_args[0]) && $pos_args[0] == '-r')
            $this->call_eressea("write_files_pw.lua");
        else {
            // run once to create reports.txt
            $this->call_eressea("write_files_pw.lua");
            $this->call_eressea("write_files_only.lua");
        }
    }

    function check_email() : bool {
        if (isset($this->config['runner']['muttrc'])) {
            if ($this->fakemail) {
                $path = getenv("PATH");
                $bin = $this->get_base_directory() . "/bin/fake";
                $this->log("set path $bin:$path");
                putenv("PATH=$bin:$path");
            }

            return true;
        }

        $this->abort("E-mail is not configured. Please set it up with the command install_mail.", StatusCode::STATUS_EXECUTION);
    }

    function install_mail(?array $pos_args = null) : void {
        $home=getenv("HOME");
        $xdgc=getenv("XDG_CONFIG_HOME");
        $muttrc = "$home/.muttrc";
        $basedir = $this->get_base_directory();
        $fetchmailrc =  "$basedir/etc/fetchmailrc";

        if (!file_exists($muttrc)) {
            $muttrc = "$home/.mutt/muttrc";
            if (!file_exists($muttrc)) {
                $muttrc = "$xdgc/mutt/muttrc";
            }
            if (!file_exists($muttrc)) {
                $muttrc = "";
            }
        }
        if (!empty($muttrc)){
            if ($this->confirm("Found a mutt configuration file $muttrc for this user. Do you want to use this?", 'N')) {
                $this->config['runner']['muttrc'] = $muttrc;
                $this->save_config();
                return;
            }

        }
        $muttrc = "$basedir/etc/.muttrc";
        if (file_exists($muttrc)) {
            if ($this->confirm("Found a mutt configuration file $muttrc. Do you want to use this?")){
                $this->config['runner']['muttrc'] = $muttrc;
                $this->save_config();
                return;
            }
        }

        do {
            if (!$this->input("I will try to create a new mutt configuration file for you.\n".
                "These are the settings for *sending* e-mails.\n".
                "What is your server's name used in the sender's address?", $name))
                exit(0);
            if (!$this->input("What is your sender's email address?", $email))
                exit(0);
            $smtp_user = $email;
            if (!$this->input("What is your SMTP user name?", $smtp_user))
                exit(0);
            if (!$this->input("What is your SMTP user passphrase?", $smtp_pw))
                exit(0);
            if (!$this->input("What is your provider's SMTP server?", $smtp_server))
                exit(0);
            $smtp_port = 465;
            if (!$this->input("What is your SMTP server port (often 465 or 587)?", $smtp_port))
                exit(0);

        } while (!$this->confirm("Here's what you have entered so far:\n" .
            "name: $name\nemail: $email\nuser: $smtp_user\npassword: ***\nserver: $smtp_server\nport: $smtp_port\n".
            "Is this correct?"));


        $protocol = 'IMAP';
        $protocol_user = $smtp_user;
        $protocol_pw = "";
        $protocol_server = $smtp_server;
        $protocol_port = 993;

        do {
            do {
                if (!$this->input("These are the settings for *receiving* e-mails.\n".
                    "Would you like to use the IMAP or the POP3 protocol?", $protocol))
                    exit(0);
                if ($protocol != "IMAP" && $protocol != "POP3")
                    echo "Unknown protocol. Please enter IMAP or POP3\n";
            } while($protocol != "IMAP" && $protocol != "POP3");
            if (!$this->input("What is your $protocol user name?", $protocol_user))
                exit(0);
            if (!$this->input("What is your $protocol user passphrase (leave blank if same as above)?", $protocol_pw))
                exit(0);
            if ($protocol_pw == '') $same = "same as SMTP"; else $same = "***";
            if (!$this->input("What is your provider's $protocol server?", $protocol_server))
                exit(0);
            if (!$this->input("What is your $protocol server port (often 993)?", $protocol_port))
                exit(0);
        } while (!$this->confirm("Here's what you have entered so far:\n" .
            "user: $protocol_user\npassword: $same\nserver: $protocol_server\nport: $protocol_port\n".
            "Is this correct?"));

        if ($protocol_pw == '') $protocol_pw = $smtp_pw;

        $templatefile = "$basedir/etc/muttrc.$protocol.template";
        if (!file_exists($templatefile))
            $this->abort("template file $templatefile not found", StatusCode::STATUS_EXECUTION);
        $template = file_get_contents($templatefile);
        if ($template == false)
            $this->abort("template file $templatefilenot found", StatusCode::STATUS_EXECUTION);

        $from = "$name <$email>";

        $backup = null;
        if (file_exists($muttrc)) {
            $backup = $muttrc . "~";
            if (rename($muttrc, $muttrc . "~"))
                $this->info("moved $muttrc to $backup");
            else {
                $this->info("could not create backup $backup");
                $backup = null;
            }
        }

        $mailfolder = "$basedir/Mail";
        $okey = file_put_contents($muttrc,
                    sprintf($template,
                        escapeshellarg($from),
                        escapeshellarg($smtp_user),
                        escapeshellarg($smtp_pw),
                        escapeshellarg($smtp_server),
                        intval($smtp_port),
                        escapeshellarg($protocol_user),
                        escapeshellarg($protocol_pw),
                        escapeshellarg($protocol_server),
                        intval($protocol_port),
                        escapeshellarg($mailfolder)));
        if ($okey === false)
            $this->abort("could not write to $muttrc", StatusCode::STATUS_EXECUTION);

        $templatefile = "$basedir/etc/fetchmailrc.template";
        if (!file_exists($templatefile))
            $this->abort("template file $templatefile not found", StatusCode::STATUS_EXECUTION);
        $template = file_get_contents($templatefile);
        if ($template == false)
            $this->abort("template file $templatefile not found", StatusCode::STATUS_EXECUTION);

        $this->info("stopping fetchmail");
        $this->cmd_fetch([ "--quit" ]);

        $okey = file_put_contents($fetchmailrc,
                    sprintf($template,
                        escapeshellarg($basedir),
                        escapeshellarg($basedir),
                        escapeshellarg($protocol_server),
                        $protocol,
                        intval($protocol_port),
                        escapeshellarg($protocol_user),
                        escapeshellarg($protocol_pw),
                        escapeshellarg($basedir),
                        escapeshellarg($basedir)
                    ));
        chmod($fetchmailrc, 0700);
        if ($okey === false)
            $this->abort("could not write to $fetchmailrc", StatusCode::STATUS_EXECUTION);

        touch("$basedir/log/fetchmail.log");
        chmod("$basedir/log/fetchmail.log", 0700);
        touch("$basedir/etc/.fetchids");
        chmod("$basedir/etc/.fetchids", 0700);

        echo "Wrote profile to $muttrc and $fetchmailrc. You may edit these files manually to make further adjustments.\n";

        mkdir($this->get_base_directory() . "/Mail");
        // TODO crontab setup

        if ($this->confirm("Start fetchmail in the background now?")) {
            $this->debug("starting fetchmail");
            $this->cmd_fetch([ "--listen" ]);
        } else {
            $this->info("You may use the fetch command to start fetchmail.");
        }

    }


    const SET_ERESSEA_PATTERN = "/ERESSEA=(.*)/";
    const SET_FETCHMAILRC_PATTERN = "/FETCHMAILRC=(.*)/";
    const TIME_PATTERN = "(([^\s])+[ \t]+([^\s])+[ \t]+([^\s])+[ \t]+([^\s])+[ \t]+([^\s])+)";
    // 15 21 * * Sat        $ERESSEA/server/bin/run-eressea.cron 1
    const RUNNER_LINE = "1 1 1 1 1    \$ERESSEA/server/bin/run-eressea.cron 999";
    const RUNNER_PATTERN = "[ \t]+((.*run-eressea.cron\s+)([A-Za-z0-9]*)(.*))";
    //*/15 * * * *          $ERESSEA/server/bin/orders.cron 1
    const CHECKER_LINE = "1 1 1 1 1    \$ERESSEA/server/bin/orders.cron 999";
    const CHECKER_PATTERN = "[ \t]+((.*orders.cron\s+)([A-Za-z0-9]*)(.*))";
    // 00 *  * * *         [ -f $FETCHMAILRC ] && fetchmail --quit -f $FETCHMAILRC >> $ERESSEA/log/fetchmail.log 2>&1
    const FETCHMAIL_LINE = "* */1 * * *    [ -f \$FETCHMAILRC ] && fetchmail --quit -f \$FETCHMAILRC >> \$ERESSEA/log/fetchmail.log 2>&1";
    const FETCHMAIL_PATTERN = "[ \t]+(.*fetchmail.*)";

    function analyze_crontab($runner_pattern, $checker_pattern) : array {
        $out = $this->exec("crontab -l");

        $info = [ 'crontab' => $out, 'runners' => [], 'checkers' => [], 'fetchmail' => [], 'other' => [] ];
        $l=0;

        foreach($out as $line) {
            if (!empty($line) && !str_starts_with($line, '#')) {
                if (preg_match($runner_pattern, $line, $matches) == 1) {
                    $info['runners'][] = [
                     'line' => $l,
                     'text' => $line,
                     'time' => $matches[1],
                     'game' => $matches[9] ];
                } else if (preg_match($checker_pattern, $line, $matches) == 1) {
                    $info['checkers'][] = [
                     'line' => $l,
                     'text' => $line,
                     'time' => $matches[1],
                     'game' => $matches[9] ];
                } else if (preg_match("/". static::TIME_PATTERN . static::FETCHMAIL_PATTERN . "/", $line, $matches) == 1) {
                    $info['fetchmail'][] = [
                     'line' => $l,
                     'text' => $line,
                     'time' => $matches[1] ];
                } else if (preg_match(static::SET_ERESSEA_PATTERN, $line, $matches)) {
                    $info['set_eressea'][] = [
                        'line' => $l,
                        'text' => $line,
                        'value' => $matches[1]
                    ];
                } else if (preg_match(static::SET_FETCHMAILRC_PATTERN, $line, $matches)) {
                    $info['set_fetchmailrc'][] = [
                        'line' => $l,
                        'text' => $line,
                        'value' => $matches[1]
                    ];
                } else {
                    $info['other'][] = $line;
                }
            }
            ++$l;
        }
        return $info;
    }


    function keep_time(array &$info) {

        $abort = function() {
            exit(StatusCode::STATUS_PARAMETER);
        };

        if ($this->confirm("Keep this?")) {
            $info['keep'] = true;
            $time = $info['time'];
            $prompt = "Time";
            while(($this->input($prompt, $time) || $abort() ) &&
                preg_match('/' . static::TIME_PATTERN . '/', $time) !== 1) {
                $prompt = "Invalid time $time. Time";
                $time = $info['time'];
            }

            $info['time'] = $time;
        }
    }

    function install_cron(array $pos_args = []) : void {
        $basedir = $this->get_base_directory();
        $clear = false;

        $runner_pattern = "/". static::TIME_PATTERN . static::RUNNER_PATTERN . "/";
        $checker_pattern = "/". static::TIME_PATTERN . static::CHECKER_PATTERN . "/";

        $runners_active = 0;
        $fetchmail_active = 0;

        foreach($pos_args as $arg) {
            if ("--list" == $arg) {
                passthru("crontab -l");
                return;
            }
            if ("--edit" == $arg) {
                passthru("crontab -e");
                return;
            }
            if ("--clear" == $arg) {
                $clear = true;
            }
        }

        $cron_info = $this->analyze_crontab($runner_pattern, $checker_pattern);

        if (!empty($cron_info['other'])) {
            $this->info("There are unknown lines in your crontab file. Changing it may be dangerous.");
        }

        if (!empty($cron_info['runners'])) {
            $this->info("There are runners installed:");
            foreach($cron_info['runners'] as &$runner) {
                $this->info('Game ' . $runner['game'] . ' time: ' . $runner['time']);
                if ($clear) {
                    $this->info("disabling");
                } else {
                    $this->keep_time($runner);
                }
            }
            unset($runner);
        }

        if (!empty($cron_info['checkers'])) {
            $this->info("There are checkers installed:");
            foreach($cron_info['checkers'] as &$runner) {
                $this->info('Game ' . $runner['game'] . ' time: ' . $runner['time']);
                if ($clear) {
                    $this->info("disabling");
                } else {
                    $this->keep_time($runner);
                }
            }
            unset($runner);
        }

        if (!empty($cron_info['fetchmail'])) {
            $this->info("Fetchmail installed:");
            if (count($cron_info['fetchmail']) > 1) {
                $this->warn('More than one fetchmail line!');
            }
            if ($clear) {
                $this->info("disabling");
            } else if ($this->confirm("Keep this?")) {
                foreach($cron_info['fetchmail'] as &$info) {
                    $info['keep'] = true;
                }
                unset($info);
            }
        }

        if (!$clear && $this->confirm("Schedule new server run?")) {
            do {
                $this->input("Game ID", $id);
            } while (empty($id));

            $mode = 'w';
            $this->input("Run weekly (w), daily (d), every n minutes (m) or input manually (i)?", $mode);
            switch($mode) {
            case 'w':
                $day = 6;
                do {
                  $this->input("Day of week (1 = monday, 7 = sunday)", $day);
                } while ($day <= 0 || $day > 7);

                $hour = 21;
                do {
                    $this->input("Hour (0-23)", $hour);
                } while ($hour < 0 || $hour > 23);
                $minute = 0;
                do {
                    $this->input("Minute (0-59)", $minute);
                } while ($minute < 0 || $minute > 59);

                $time = "$minute $hour * * " . [ 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun
                '][$day-1];

            break;
            case 'd':
                $hour = 21;
                do {
                    $this->input("Hour (0-23)", $hour);
                } while ($hour < 0 || $hour > 23);
                $minute = 0;
                do {
                    $this->input("Minute (0-59)", $minute);
                } while ($minute < 0 || $minute > 59);

                $time = "$minute $hour * * *";

            break;

            case 'm':
                $interval = 60;
                do {
                    $this->input("Minutes (1-1440)", $interval);
                } while ($interval < 1 || $interval > 1440);

                $time = "*/$interval * * * *";

            break;

            case 'i':
                $time = get_time($time);

            break;
            }
            $runner = [ 'time' => $time, 'new' => true, 'id' => $id ];

            if ($mode == 'm') {
                $this->info("Checkers for games running every x minutes are not recommended.");
            } else if ($this->confirm("Add checker for this game?")) {
                $interval = 15;
                do {
                    $this->input("Run every ... minutes (1-1440)", $interval);
                } while ($interval < 1 || $interval > 1440);

                $runner['checker']['time'] = "*/$interval * * * *";
            }
            ++$runners_active;
            $cron_info['runners'][] = $runner;
        }


        $lines = [];
        foreach($cron_info['runners'] as $info) {
            if (isset($info['keep'])) {
                $time = $info['time'];
                $game = $info['game'];
                $lines[$info['line']] = preg_replace($runner_pattern, "$time \${8}$game\${10}", $info['text']);
                ++$runners_active;
            } else if (isset($info['line'])) {
                $lines[$info['line']] = "# " . $info['text'];
            }
        }


        foreach($cron_info['checkers'] as $info) {
            if (isset($info['keep'])) {
                $time = $info['time'];
                $game = $info['game'];
                $lines[$info['line']] = preg_replace($checker_pattern, "$time \${8}$game\${10}", $info['text']);
            } else if (isset($info['line'])) {
                $lines[$info['line']] = "# " . $info['text'];
            }
        }


        foreach ($cron_info['fetchmail'] as $info) {
            if (isset($info['keep'])) {
                $lines[$info['line']] = $info['text'];
                ++$fetchmail_active;
            } else if (isset($info['line'])) {
                $lines[$info['line']] = "# " . $info['text'];
            }
        }

        $i = 0;
        $tab = "";

        if ($runners_active > 0 && empty($cron_info['set_eressea'])) {
            $tab .= "\n# Environment variables are not visible from cron\n" .
                "# setup ERESSEA variable\n";
            $tab .= "ERESSEA=$basedir\n";
            $tab .= "FETCHMAILRC=$basedir/etc/fetchmailrc\n\n";
        }

        foreach($cron_info['crontab'] as $line) {
            if (isset($lines[$i])) {
                $tab .= $lines[$i] . "\n";
            } else {
                $tab .= $line . "\n";
            }
            ++$i;
        }

        foreach($cron_info['runners'] as $info) {
            if (isset($info['new'])) {
                $game = $info['id'];
                $time = $info['time'];
                $tab .= "\n#runner for game $game\n";
                $tab .=  preg_replace($runner_pattern, "$time \${8}$game\${10}", static::RUNNER_LINE) . "\n\n";

                if (isset($info['checker']['time'])) {
                $time = $info['checker']['time'];
                $tab .= "#checker for game $game\n";
                $tab .=  preg_replace($checker_pattern, "$time \${8}$game\${10}", static::CHECKER_LINE) . "\n\n";
                }
            }
        }

        if ($runners_active > 0 && $fetchmail_active == 0) {
            if ($this->confirm("Do you want to start fetchmail automatically?")) {
                $tab .= "\n# start fetchmail demon\n";
                $tab .= static::FETCHMAIL_LINE ."\n";
            }
        }
        $tab .= "\n";

        $this->info("------------------------------------\n$tab");

        if ($this->confirm("\nAbove is your new crontab. Install it?")) {
            $filename = "$basedir/etc/crontab.installed";
            file_put_contents($filename, $tab);
            $this->exec("crontab $filename");
        }

    }

    function cmd_send(array $pos_args) :void {
        $this->goto_game();
        $this->chdir("reports");
        if (!file_exists("reports.txt")) {
            $this->abort("missing reports.txt for game " . $this->game_id, StatusCode::STATUS_EXECUTION);
        }

        $this->check_email();

        $this->call_script("compress.sh", [$this->game_id]);

        if (empty(glob("[a-kLm-z0-9]*.sh"))) {
            $this->abort("no send scripts in " . getcwd(), StatusCode::STATUS_EXECUTION);
        }

        // trick mutt into finding its configuration
        $oldhome = getenv("HOME");
        putenv("HOME=" . dirname($this->config['runner']['muttrc']));

        if (empty($pos_args)) {
            $this->call_script("sendreports.sh", [$this->game_id]);
        } else {
            for ($pos = 0; $pos < count($pos_args); ) {
                $fid = $pos_args[$pos++];
                $scriptname = "$fid.sh";
                if (!is_executable($scriptname)) {
                    chmod($scriptname, 0770);
                }
                if (is_executable($scriptname)) {
                    $email = $pos_args[$pos] ?? null;
                    if (strpos($email, "@") !== false) {
                        ++$pos;
                        $this->call_script(realpath($scriptname), [ $email ]);
                    } else
                        $this->call_script(realpath($scriptname), [ ]);
                } else {
                    $this->info("no send script for faction $fid");
                }
            }
        }
    }

    function cmd_fetch($pos_args) {
        $this->check_email();
        $this->chdir($this->get_base_directory());

        $fetchmailrc = "etc/fetchmailrc";
        $procmailrc = "etc/procmailrc";
        if (!file_exists($fetchmailrc))
            $this->abort("Fetchmail configuration $fetchmailrc not found.", StatusCode::STATUS_EXECUTION);
        if (!file_exists($procmailrc))
            $this->abort("Procmail configuration $procmailrc not found.", StatusCode::STATUS_EXECUTION);


        $fargs = '';
        foreach ($pos_args as $arg) {
            switch ($arg) {
                case '--once':
                $fargs .= " -1";
                break;

                case '--listen':
                $fargs .= " -l";
                break;

                case '--quit':
                $fargs .= " -q";
                break;

                default:
                $this->abort("unknwon argument $arg", StatusCode::STATUS_PARAMETER);
                break;
            }
        }


        $out = $this->exec("bin/start_fetchmail.sh $fargs '$fetchmailrc'");
        foreach($out as $line)
            $this->info($line);
    }

    function cmd_create_orders() {
        $this->goto_game();
        $turn = $this->get_current_turn();


        if (!is_dir("orders.dir") || empty(glob("orders.dir/turn-*")))
            $this->abort("no orders in orders.dir", StatusCode::STATUS_EXECUTION);

        // TODO check NMRs
        $this->call_script("create-orders", [ $this->game_id, $turn ]);

        $this->info("order reception for turn $turn is closed, orders sent now will be for the next turn");

        if (!is_dir("orders.dir.$turn"))
            $this->error("orders.dir.$turn was not created");

    }

    function cmd_run() {
        $this->goto_game();
        $game = $this->game_id;
        $turn = $this->get_current_turn();

        if (!is_file("orders.$turn"))
            $this->abort("no order file orders.$turn", StatusCode::STATUS_EXECUTION);

        // TODO check NMRs
        $out = [];
        $this->call_eressea('./scripts/run-turn.lua', [ '-t', "$turn", '-l', '5' ], $out);
        $text = "";
        foreach($out as $line)
            $text .= "$line\n";

        $this->info("$text\n");

        // $this->call_script("run-turn", [ $this->game_id, $turn ]);

        if (!file_exists("log/eressea.log.$game.$turn"))
            link("eressea.log", "../log/eressea.log.$game.$turn");
    }

    private function backup_file(string $filename) : string {
        for($i = 0; ; ++$i) {
            $backupname = $filename . "~$i~";
            if (!file_exists($backupname)) {
                rename($filename, $backupname);
                break;
            }
        }
        return $backupname;
    }

    private function sd(array &$c, array $path, int $i, mixed $default) : void {
       $step = $path[$i];
        if(!isset($c[$step])) {
            $c[$step] = ($i+1 == count($path)) ? $default : [];
        }
        if ($i+1 < count($path))
            $this->sd($c[$step], $path, $i+1, $default);
    }

    private function set_default(array &$config, array $path, mixed $default) : void {
        $this->sd($config, $path, 0, $default);
    }

    private function parse_json(string $configfile) : array|null {
        $config = null;
        if (file_exists($configfile) && is_readable($configfile)) {
            $raw = file_get_contents($configfile);
            if (empty($raw)) {
                $this->error("Could not read config file '$configfile'");
            } else {
                $config = json_decode($raw, true);
                if ($config == null)
                    $this->error("Invalid config file '$configfile'");
            }
        } else {
            $this->error("Config file '$configfile' not found");
        }
        return $config;
    }

    private const CONFIG_VARIABLES = ['runner', 'game'];

    function parse_php(string $configfile) : array|null {
        if (!is_readable($configfile))
            return null;
        include($configfile);
        $config = [];
        foreach(static::CONFIG_VARIABLES as $section) {
            $name = "ER$section";
            $config[$section] = $$name;
        }
        return $config;
    }

    function php_encode(array $content) {
        $text = "<?php\n";
        foreach(static::CONFIG_VARIABLES as $section) {
            if (isset($content[$section]))
                $text .= "\$ER$section = " . var_export($content[$section], true) . ";\n";
            else
                $text .= "\$ER$section = [ ];\n";
        }
        return $text;
    }

    function save_php(string $configfile, array $content) : bool {
        if (empty($configfile) || (file_exists($configfile) && !is_writable($configfile))) {
            $this->error("Cannot write to config file '$configfile");
            return false;
        } else {
            file_put_contents($configfile, $this->php_encode($content), LOCK_EX);
            $this->debug("Wrote config file $configfile");
            return true;
        }
    }

    function parse_config(string $configfile) : array {
        $config = $this->parse_php($configfile);

        if (empty($config))
            $config = [];

        $this->set_default($config, ['configfile'], $configfile);
        $this->set_default($config, ['runner', 'basedir'], dirname(dirname($configfile)));
        $config['runner']['basedir'] = realpath($config['runner']['basedir']);
        $this->set_default($config, ['runner', 'logfile'], 'log/runner.log');
        $this->set_default($config, ['runner', 'serverdir'], 'server');
        $this->set_default($config, ['runner', 'gamedir'], '.');

        $this->config = $config;
        return $config;
    }

    function save_json(string $configfile, array $content) : bool{
        if (empty($configfile) || (file_exists($configfile) && !is_writable($configfile))) {
            $this->error("Cannot write to config file '$configfile");
            return false;
        } else {
            file_put_contents($configfile, json_encode($content, JSON_PRETTY_PRINT), LOCK_EX);
            $this->debug("Wrote config file $configfile");
            return true;
        }
    }

    function save_config() : void {
        $configfile = $this->config['configfile'];
        $this->info("save $configfile\n");
        $copy = $this->config;
        unset($copy['configfile']);
        $this->save_php($configfile, $copy);
        // $this->save_json($configfile, $copy);
    }

    function get_base_directory() : mixed {
        return $this->config['runner']['basedir'];
    }

    function get_server_directory() : string {
        return $this->get_base_directory() . "/" . $this->config['runner']['serverdir'];
    }

    function get_game_directory(string $id = null) : string {
        $gamedir = realpath($this->get_base_directory() . "/" . $this->config['runner']['gamedir']);
        if ($id == null)
            return $gamedir;
        else
            return $gamedir . "/game-" . $id;
    }
}

function get_logger(?array $config, int $log_level) : Logger {
    if (!isset($config['runner']))
        $logfile = NULL;
    else {
        $logfile = $config['runner']['logfile'] ?? ($config['runner']['basedir'] . "/log/runner.log") ?? NULL;
        if (!str_starts_with($logfile, "/"))
            $logfile = $config['runner']['basedir'] . "/" . $logfile;
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
$log_level = Logger::INFO;
$verbosity = 1;
$scriptname = $_SERVER['PHP_SELF'];

if (realpath($scriptname) !== false)
    $configfile = dirname(dirname(realpath($scriptname))) . "/conf/config.php";
else
    $configfile = realpath(".") . "/conf/config.php";
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
    } elseif ('-g' === $arg) {
        $runner->set_game($argv[++$optind]);
    } elseif ('-t' === $arg) {
        $runner->set_turn($argv[++$optind]);
    } elseif ('-y' === $arg) {
        $runner->confirm_all = true;
    } elseif ('--fakemail' == $arg) {
        $runner->set_fakemail(true);
    } else if (str_starts_with($arg, "-")) {
        if ($verbosity > 0)
            echo "unknown option '$arg'\n";
        $usage = 1;
    } else {
        break;
    }
    ++$optind;
}
$runner->set_scriptname($scriptname ?? $argv[0]);
if ($usage) {
    $runner->usage(true, StatusCode::STATUS_NORMAL);
}

$runner->set_verbosity($verbosity);

$logger = get_logger(null, $log_level);
$config = $runner->parse_config($configfile);
$logger = get_logger($config, $log_level);

if ($config) {
    $logger->info("Config file '$configfile' read");
    $logger->debug($config);
} else {
    $logger->warning("No config file '$configfile'\n");
}
$logger->debug("Parameters:\n");
$logger->debug($argv);

$pos_args = array_slice($argv, $optind);

$command = $pos_args[0] ?? 'help';
array_shift($pos_args);

$logger->info("command $command");

if ('help' == $command) {
    $runner->usage(false, StatusCode::STATUS_NORMAL, $pos_args[0] ?? NULL);
} elseif ('install' == $command) {
    $runner->cmd_install($pos_args);
} elseif ('create_config' == $command) {
    $runner->save_config();
} else {
    if (empty($configfile) || !file_exists($configfile)) {
        $msg = "Config file '$configfile' not found.";
        $logger->error($msg);
        if ($verbosity > 0) {
            $runner->usage(true, false, 'config_not_found');
        }
        exit(StatusCode::STATUS_PARAMETER);
    } elseif ((EresseaRunner::COMMANDS[$command]['std_runner'] ?? false) == true) {
        $runner->cmd($command, $pos_args);
    } elseif ('info' == $command) {
        $runner->cmd_info($configfile, $argv, $pos_args);
    } else {
        $msg = "unknown command '$command'";
        if ($verbosity > 0)
            echo "$msg\n";
        $logger->error($msg);
        $runner->usage(true, StatusCode::STATUS_PARAMETER);
    }
}
