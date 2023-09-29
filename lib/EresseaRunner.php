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
    public int $game_id = -1;
    public int $turn = -1;


    const GAME_ID_PATTERN = "/^[\p{L}\p{N}]+$/";

    function set_scriptname(string $name) : void {
        $this->scriptname = $name;
    }

    const COMMANDS = [
        'help' => [
            'std_runner' => true,
            'commandline' => "help [<command>]",
            'short' =>  'display help information; try help <command> or help config for information about a command or the configuration file'],
        'install' => [
            'commandline' => 'install [<branch>] [<server directory>] [<games directory>]',
            'short' => 'install the server' ],
        'game' => [
            'std_runner' => true,
            'commandline' => 'game <rules>',
            'short' => 'start a new game'],
        'upgrade' => [
            'commandline' => 'upgrade <branch>',
            'short' => 'recompile and install from source'],
        'install_mail' => [
            'std_runner' => true,
            'short' => 'setup e-mail configuration' ],
        'eressea' => [
            'std_runner' => true,
            'commandline' => "eressea [args ...]",
            'short' => 'call the eressea server'],
        'gmtool' => [
            'std_runner' => true,
            'short' => 'open the game editor' ],
        'seed' => [
            'std_runner' => true,
            'commandline' => 'seed [-r] [<algorithm>]',
            'short' => 'seed new players' ],
        'reports' => [
            'std_runner' => true,
            'commandline' => 'reports [-p]',
            'short' => 'write reports for all factions' ],
        'send' => [
            'std_runner' => true,
            'commandline' => 'send [<faction id> ...]',
            'short' => 'send reports for all or some factions' ],
        'fetch' => [
            'short' => 'fetch orders from mail server' ],
        'create_orders' => [
            'short' => 'process all orders in queue'],
        'run' => [
            'short' => 'run a turn' ],
        'run_all' => [
            'short' => 'do a complete cycle: fetch mail, create-orders, run, send' ],
        'announce' => [
            'commandline' => 'announce subject textfile [attachement ...]',
            'short' => 'send a message to all players' ],
        'backup' => [
            'short' => 'backup relevant data for this turn' ]
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
            if (!empty($info))
                $info .= "\n";
            $words = explode(" ", $line);
            $info .= $prefix;
            $pos = mb_strlen($prefix);
            foreach ($words as $word) {
                if ($pos != 0 && $pos + mb_strlen($word) > $linewidth) {
                    $info .= "\n$prefix";
                    $pos = mb_strlen($prefix)+1;
                } elseif ($pos > mb_strlen($prefix)) {
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
            echo <<<EOL
USAGE
    $name [-f <configfile>] [-y] [-l <log level>] [-v <verbosity>]
        [-g <game-id>] [-t <turn>] <command> [<args> ...]

    <configfile> contains all the game settings. Defaults to config.ini in the script's directoy.

    -l <log level>
    -v <verbosity>
        0 = quiet, 1 = normal, 2 = verbose, 3 = debug
    -y:
        do not ask for confirmation or user input whenever possible

    Use $name help commands to get a list of all commands
    and $name help <command> for help about a command.


EOL;
        } elseif ('config_not_found' == $command) {
            echo "    You can create a config file with the install or the create_config command.\n";
            echo "    You can manually set the location of the config file with the -c parameter.\n";
        } else {
            echo "USAGE:\n";
            if ('commands' == $command) {
                echo <<<EOL
COMMANDS

    These commands are available:

EOL;
                foreach(static::COMMANDS as $cmd => $cinfo) {
                    echo $this->short_info($cmd);
                    echo "\n";
                }
            } else if ("game" == $command) {
                echo $this->get_cmd_line($command);
                echo "        you can use -g and -t to set the game ID and start turn, respectively\n";
                echo "        <rules> is the rule set (e2 or e3)\n";
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
            } else if ("seed" == $command) {
                echo $this->get_cmd_line($command);
                echo $this->line_break(<<<EOL
This command reads the newfactions file in the game directory. This file contains one line for each new faction. A line contains an email address, a race, a langugage code (de or en), and optional additonal parameters all separated by one or more white space charaters. Lines starting with # are ignored.

It then uses the given algorithm to create a new map and place the new factions on it. It also creates a file "autoseed.json" in the game directory. You can edit the file to change the behavior of the seeding algorithm.

If you don't specify an algorithm or if the algorithm in the autoseed.json file matches the given algorithm, the file is read and the algorithm from the configuration file is executed with the given parameters.

The generated map is saved to auto.dat in the data directory. If the -r option is given, it the current turn data in 'data/<turn>.dat' is also replaced with the generated map.

EOL, "    ");

            } else if ("reports" == $command) {
                echo $this->get_cmd_line($command);
                echo $this->line_break(<<<EOL
Write all report files. With -p it also re-generates all the passwords and writes them back to the report. Note that, if you run it without -p, there will be no new password messages in the reports and no passwords in the turn templates for new factions.

EOL, "    ");
            } else if ("config" == $command) {
                echo <<<EOL
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

            } elseif (isset(EresseaRunner::COMMANDS[$command])) {
                echo $this->short_info($command);
                echo "\n";
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

    function cmd_info(string $configfile, array $argv, array $pos_args) : void {
        $this->info("\nworking directory: '" . getcwd() . "'\n");
        $this->info("config: '$configfile'\n");
        foreach ($pos_args as $key => $value) {
            $this->log("arg[$key]: '$value'\n");
        }

        if (empty($pos_args[0])) {
            $this->error("missing argument");
            // $this->usage($argv[0], 1);
        }

        $this->info("\nbase dir: '" . $this->config['runner']['basedir'] . "'\n");
        $this->info("log file: '" . $this->config['runner']['logfile'] ."'\n");
        $this->info("server: '" . $this->config['runner']['serverdir'] . "'\n");
        $this->info("games: '" . $this->config['runner']['gamedir'] . "'\n\n");

    }

    public function set_game(string $id) : void {
        if (preg_match(static::GAME_ID_PATTERN, $id) !== 1)
            $this->abort("Game ID should be a positive integer ($id)", StatusCode::STATUS_PARAMETER);

        $this->game_id = $id;
    }

    public function get_current_game() : string {
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
                return -1;
            }
            $this->debug("Found game dir $id");
            return $id;
        } else {
            $this->debug("Apparently not in $gamedir/game-id");
        }
        return -1;
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
        if (!file_exists($turnfile))
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

    private function input(string $prompt, string &$value) : bool {
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
            $ms = "Could not enter directory $dirname";
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

        $this->info("done");
        foreach($out as $line)
            $this->debug($line);

        if ($result != 0) {
            $this->abort(empty($error_msg)?"$cmd exited abnormally.\n":$error_msg, $exitonfailure?StatusCode::STATUS_EXECUTION:StatusCode::STATUS_GOOD);
        }

        return $out;
    }

    function cmd_install(array $pos_args, bool $update = false) : void {
        $basedir = $this->get_base_directory();


        // TODO check dependencies git cmake ...?

        $branch = $pos_args[0] ?? 'master';
        if (!$this->input("Install branch: ", $branch))
            exit(StatusCode::STATUS_NORMAL);
        if ($branch !== 'master')
            if (!$this->confirm("Installing versions other than the master branch is unsafe. Continue?"))
                exit(StatusCode::STATUS_NORMAL);

        $installdir = $pos_args[1] ?? $basedir . '/server';
        // if (!$this->input("Install server in ", $installdir))
        //     exit(StatusCode::STATUS_NORMAL);
        $gamedir = $pos_args[2] ?? $basedir;
        // if (!$this->input("Install games in ", $gamedir))
        //     exit(StatusCode::STATUS_NORMAL);

        if (!str_starts_with($installdir, "/"))
            $installdir = $basedir . "/" . $installdir;
        if (!str_starts_with($gamedir, "/"))
            $gamedir = $basedir . "/" . $gamedir;

        if (!$this->confirm("Install branch $branch of server in $installdir, games in $gamedir?"))
            exit(StatusCode::STATUS_NORMAL);

        if (!$update && file_exists($installdir)) {
            $msg = "Installation directory exits, please use the update command";
            abort($msg, StatusCode::STATUS_NORMAL);
        }

        // does not work!
        // $this->exec("cd $basedir/eressea-source");
        $this->chdir("$basedir/eressea-source");

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

        $this->chdir($basedir);
        $this->save_config();

        if ($this->confirm("Would you also like to setup e-mail?")) {
            $this->cmd_install_mail();
        }
    }

    const INI_TEMPLATE = <<<EOF
[game]
locales = de,en
id      = %d
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
        if ($game_id == -1) {
            for($game_id = 0; ; ++$game_id) {
                $gamesub = "game-$game_id";
                if (!file_exists("$gamedir/$gamesub")) {
                    break;
                }
            }
        }

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
        $config =  "$serverdir/conf/$rules/config.json";
        if (!file_exists($config))
            $this->abort("Cannot find config $config", StatusCode::STATUS_PARAMETER);


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
        if ($this->game_id != -1) {
            $gameid = $this->game_id;
        } else {
            $gameid = $this->get_current_game();
            if ($gameid === -1) {
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
            // TODO eval ($luarocks path) ??

            $paths=$this->exec("luarocks path");
            foreach($paths as $line) {
                $line = substr($line, 7);
                putenv($line);
            }

            $lua_path=getenv("LUA_PATH");
            if ($lua_path === false) $lua_path = '';
            putenv("LUA_PATH=" . $this->get_base_directory() . "/scripts/?.lua;./?.lua;$lua_path");
            exec('echo $LUA_PATH', $out);
            $this->lua_path = $out[0];
        }
    }

    function call_eressea(string $script, array $args = []) : bool {
        $this->set_lua_path();

        if (empty($script)) {
            $scriptname = '';
        } elseif (strpos($script, ".") === 0) {
            $scriptname = $script;
        } else {
            $scriptname = $this->get_base_directory() . "/scripts/$script";
        }
        $argstring = "";

        foreach($args as $arg) {
            $argstring .= " " . escapeshellarg($arg);
        }
        echo "./eressea $argstring $scriptname\n";
        return passthru("./eressea $argstring $scriptname") === null;
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

        putenv('ERESSEA=' . $this->get_base_directory());

        $argstring = "";

        foreach($args as $arg) {
            $argstring .= " " . escapeshellarg($arg);
        }
        echo "$scriptname $argstring\n";
        return passthru("$scriptname $argstring") ===   null;
    }

    function cmd_editor(array $pos_args) : void {
        $this->goto_game();

        $this->call_eressea("editor.lua");
    }

    function cmd_reports(array $pos_args) : void {
        $this->goto_game();

        $this->info("deleting old reports");
        $this->exec("rm -rf reports");

        $this->info("writing new files");
        if (isset($pos_args[0]) && $pos_args[0] == '-r')
            $this->call_eressea("write_files_only.lua");
        else
            $this->call_eressea("write_files_pw.lua");
    }

    function check_email() : bool {
        if (isset($config['runner']['muttrc']))
            return true;

        $this->abort("E-mail is not configured. Please set it up with the command install_mail.", StatusCode::STATUS_EXECUTION);
    }

    function cmd_install_mail(array $pos_args = null) : void {
        $home=getenv("HOME");
        $xdgc=getenv("XDG_CONFIG_HOME");
        $muttrc = "$home/.muttrc";
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
        $muttrc = $this->get_base_directory() . "/etc/.muttrc";
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
            "email: $email\nuser: $smtp_user\npassword: ***\nserver: $smtp_server\nport: $smtp_port\n".
            "Is this correct?"));


        $protocol = 'IMAP';
        $protocol_user = $smtp_user;
        $protocol_pw = "";
        $protocol_server = $smtp_server;
        $protocol_port = 993;

        do {
            do {
                if (!$this->input("These are the settings for *receiving* e-mails.\n".
                    "Would you like to use the IMAP or the POP protocol?", $protocol))
                    exit(0);
                if ($protocol != "IMAP" && $protocol != "POP")
                    echo "Unknown protocol. Please enter IMAP or POP\n";
            } while($protocol != "IMAP" && $protocol != "POP");
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

        $templatefile = $this->get_base_directory() . "/etc/muttrc.$protocol.template";
        if (!file_exists($templatefile))
            $this->abort("template file $templatefile not found", StatusCode::STATUS_EXECUTION);
        $template = file_get_contents($templatefile);
        if ($template == false)
            $this->abort("template file not found", StatusCode::STATUS_EXECUTION);

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

        $mailfolder=$this->get_base_directory() . "/Mail";
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

        echo "Wrote profile to $muttrc. You may edit this file manually to make further adjustments.\n";
    }

    function cmd_send(array $pos_args) :void {
        if (count($pos_args) > 0)
            $this->abort("additional arguments not implemented", StatusCode::STATUS_PARAMETER);

        $this->goto_game();
        $this->chdir("reports");
        if (!file_exists("reports.txt"))
            $this->abort("missing reports.txt for game " . $this->$game_id);

        $this->check_email();

        $this->call_script("compress.sh", [$this->game_id]);

        if (empty(glob("[a-kLm-z0-9]*.sh")))
            $this->abort("no send scripts in " . getcwd(), StatusCode::STATUS_EXECUTION);

        $this->call_script("sendreports.sh", [$this->game_id]);

  //         sent=0
  // assert_dir $GAMEDIR/reports
  // cd $GAMEDIR/reports
  // [ -f reports.txt ] || abort "missing reports.txt for game $game"
  // for faction in $* ; do
  //   send_report $faction
  //   sent=1
  // done
  // if [ $sent -eq 0 ]; then
  //   for faction in $(cat reports.txt | cut -f1 -d: | cut -f2 -d=); do
  //     send_report $faction
  //   done
  // fi

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

    function parse_json(string $configfile) : array|null {
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

    function parse_config(string $configfile) : array {
        $config = $this->parse_json($configfile);

        if (empty($config))
            $config = [];

        $this->set_default($config, ['configfile'], $configfile);
        $this->set_default($config, ['runner', 'basedir'], dirname($configfile));
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
        $this->save_json($configfile, $copy);
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
    } elseif ('-g' === $arg) {
        $runner->set_game($argv[++$optind]);
    } elseif ('-t' === $arg) {
        $runner->set_turn($argv[++$optind]);
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
} elseif ('update' == $command) {
    $runner->cmd_install($pos_args, true);
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
