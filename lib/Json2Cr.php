<?php

function atoi36($id) {
    return intval(base_convert("$id", 36, 10));
}

function itoa36($i) {
    return base_convert("$i", 10, 36);
}

class Json2CR {

    private $terrains = [ 'ocean' => 'Ozean', 'plain' => 'Ebene', 'swamp' => 'Sumpf', 'desert' => 'Wüste', 'highland' => 'Hochland', 'mountain' => 'Bergeq', 'glacier' => 'Gletscher', 'iceberg' => 'Eisberg', 'iceberg_sleep' => 'Gletscher', 'firewall' => 'Feuerwand', 'fog' => 'Nebel', 'thickfog' => 'Dichter Nebel', 'volcano' => 'Vulkan', 'activevolcano' => 'Aktiver Vulkan', 'hell' => 'Ebene aus Feuer und Dunkelheit', 'hall1' => 'Halle', 'corridor1' => 'Gang', 'wall1' => 'Wand'];

    private array|null $world;
    private $outputFile;

    const VERSION = 69;
    const MIN_VERSION = 69;
    const MAX_VERSION = 69;

    const ASTRAL_OFFSET_X = 1000;
    const ASTRAL_OFFSET_Y = 1000;
    const ASTRAL_WIDTH = 1000;
    const ASTRAL_HEIGHT = 1000;

    public function error(string $msg) {
        fprintf(STDERR, $msg . "\n");
        exit(2);
    }

    public function warning(string $msg) {
        fprintf(STDERR, $msg . "\n");
    }

    public function readJson($inputFile) {
        // Read from the input file and write to the output file (streaming)
        $jsoninput = "";
        while (!feof($inputFile)) {
            $line = fgets($inputFile); // Read a line from the input file
            if ($line !== false) {
                $jsoninput .= $line;
            }
        }
        $this->world = json_decode($jsoninput, true);
        if ($this->world === null) {
            $this->error("Invalid json input\n");
            exit(1);
        }
    }

    public function writeCr($outputFile) {
        $this->outputFile = $outputFile;
        $this->write_header();
        if (isset($this->world['factions']))
            foreach ($this->world['factions'] as $key => $value) {
                $this->write_faction($key, $value);
            }
            if (isset($this->world['regions']))
                foreach ($this->world['regions'] as $key => $value) {
                    $this->write_region($key, $value);
                }

            }


            private function write_header() {
                $this->writeBlock("VERSION", [self::VERSION]);
                $this->writeTag("UTF-8", "charset");
                $this->writeTag("de", "locale");
                $this->writeTag(time(), "date");
        // TODO Spiel
                $this->writeTag("Standard", "Konfiguration");
                $this->writeTag("Hex", "Koordinaten");
                $this->writeTag(36, "Basis");
                $this->writeTag(1, "Runde");
            }

            private function write_faction($id, $faction) {
                $this->writeBlock("PARTEI", [atoi36($id)]);
                $this->writeTag($faction['name'], "Parteiname");
                $this->writeTag($faction['email'], "email");
            }

            private function set_plane($x, $y) {
                foreach ($this->world['planes'] as $key => $plane) {
            if ($x >= $plane['x'] && $x < $plane['x'] + $plane['width'] && //
                $y >= $plane['y'] && $y < $plane['y'] + $plane['height']) {
                $x = $x -$plane['x'] - $plane['width'] / 2;
                $y = $y -$plane['y'] - $plane['height'] / 2;
                return ([$x, $y, $key]);
            }
        }
        return [$x, $y];
    }


    private function write_region($id, $region) {
        $x = $region['x'];
        $y = $region['y'];
        $coords = $this->set_plane($x, $y);
        $this->writeBlock("REGION", $coords);
        $this->writeTag($id, "id");
        if (isset($region['name']))
            $this->writeTag($region['name'], "Name");
        $this->writeTag($this->terrains[$region['type']], "Terrain");
    }

    private function writeBlock($name, $pars) {
        $line = "$name";
        foreach ($pars as $num => $par) {
            $line .= " $par";
        }
        $line .= "\n";
        fwrite($this->outputFile, $line);
    }

    private function writeTag($value, $tag) {
        // TODO escape something??
        $line = gettype($value) == "integer" ? $value : "\"$value\"";
        $line .= ";$tag\n";
        fwrite($this->outputFile, $line);
    }

    private function isBlock($line, $name, &$pars)
    {
        if (preg_match("/^$name((  *([0-9]+))*)$/", $line, $matches)) {
            $pars = array_slice(preg_split("/\s+/", $matches[1]), 1);
            return true;
        }
        return false;
    }

    function terrains2type($terrain) {
        return array_search($terrain, $this->terrains);
    }

    public function add_plane($id, $x, $y, $w, $h, $name) {
        if (!isset($this->world['planes']["$id"])) {
            $this->world['planes']["$id"] = [ 'x' => $x, 'y' => $y, 'width' => $w, 'height' => $h];
            if (!empty($name))
                $this->world['planes']["$id"]['name'] = $name;
        }
    }

    public function readRegion($inputFile, &$line, &$block, $pars) {
        $block[] = "REGION";
        $x = $pars[0];
        $y = $pars[1];
        $z = $pars[2] ?? null;
        if ($x >= self::ASTRAL_OFFSET_X || $y >= self::ASTRAL_OFFSET_Y) {
            $this->warning("very big coordinates: $x $y");
        }

        if ($z !== null) {
            if ($z == 1) {
                $this->add_plane(1, self::ASTRAL_OFFSET_X - self::ASTRAL_WIDTH / 2, self::ASTRAL_OFFSET_Y - self::ASTRAL_HEIGHT / 2, self::ASTRAL_WIDTH, self::ASTRAL_HEIGHT, "Astralraum");

                $x += self::ASTRAL_OFFSET_X;
                $y += self::ASTRAL_OFFSET_Y;
            }
        }

        while (($line = fgets($inputFile)) !== false) {
            if ($this->isBlock($line, "[A-Z]*", $pars)) {
                $this->world['regions']["".$id] = ['x' => intval($x), 'y' => intval($y), 'type' => $type];
                if (!empty($name))
                    $this->world['regions'][$id]['name'] = $name;
                return;
            }

            if (preg_match('/^(.*);(.*)$/', $line, $matches)) {
                $tag = $matches[2];
                $value = $matches[1];
                if (preg_match('/^"(.*)"$/', $value, $matches))
                    $value = $matches[1];
                if ('id' == $tag) {
                    $id = $value;
                } elseif ('Name' == $tag) {
                    $name = $value;
                } elseif ('Terrain' == $tag) {
                    $type = $this->terrains2type($value);
                }
            }
        }
    }

    public function readCr($inputFile) {
        $version = 0;
        $block = null;
        $pars = null;
        $this->world = [ "planes" => [], "regions" => [] ];
        $line = false;
        if (!feof($inputFile))
            $line = fgets($inputFile); // Read a line from the input file
        while ($line !== false) {
            if ($block == null && $this->isBlock($line, "VERSION", $pars)) {
                $block[] = "VERSION";
                $version = $pars[0];
                if ($version < self::MIN_VERSION || $version > self::MAX_VERSION) {
                    $this->warning(sprintf("unknown version %d (MIN_VERSION = %d, MAX_VERSION = %d)\n", $version, self::MIN_VERSION, self::MAX_VERSION));
                }
            } elseif (count($block) == 1 && $this->isBlock($line, "REGION", $pars)) {
                $this->readRegion($inputFile, $line, $block, $pars);
                array_pop($block);
            } else {
                $line = fgets($inputFile);
            }
        }
    }

    public function writeJson($outputFile) {
        fwrite($outputFile, json_encode($this->world, JSON_PRETTY_PRINT));
    }
}

$scriptname = $_SERVER['PHP_SELF'];
if ($argc > 2 && $argv[1] == '-c') {
    $scriptname = $argv[2];
    $argv = array_slice($argv, 2);
    $argc -= 2;
}
$scriptname = basename($scriptname);

$usage = -1;
$reverse = false;
$optind=1;
while (isset($argv[$optind])) {
    $arg = $argv[$optind];
    if (in_array($arg, ['-h', '--help'])) {
        $usage = 0;
        break;
    } elseif ('-r' === $arg) {
        $reverse = true;
    } else if (str_starts_with($arg, "-")) {
        if ($verbosity > 0)
            echo "unknown option '$arg'\n";
        $usage = 1;
        break;
    } else {
        break;
    }
    ++$optind;
}

$pos_args = array_slice($argv, $optind);

if (count($pos_args) != 2 && count($pos_args) != 0 || $usage !== -1) {
    echo "usage: $scriptname [-h] [<input.json> <output.cr>]\n";
    exit($usage);
}

if ($argc - $optind == 2) {
    $inputFilename = $pos_args[0];
    $outputFilename = $pos_args[1];


    // Check if the input file exists
    if (!file_exists($inputFilename)) {
        echo "Input file not found: $inputFilename\n";
        exit(1);
    }

    // Open the input file for reading
    $inputFile = fopen($inputFilename, 'r');
    if ($inputFile === false) {
        echo "Error opening input file: $inputFilename\n";
        exit(1);
    }

    // Open the output file for writing
    $outputFile = fopen($outputFilename, 'w');
    if ($outputFile === false) {
        echo "Error opening output file: $outputFilename\n";
        fclose($inputFile);
        exit(1);
    }
} else {
    $inputFile = fopen("php://stdin", 'r');
    if ($inputFile === false) {
        echo "Error reading from stdin";
        exit(1);
    }
    $outputFile = fopen("php://stdout", 'w');
    if ($outputFile === false) {
        echo "Error writing to stdout";
        exit(1);
    }
}

$j2c = new Json2CR;

if ($reverse) {
    $j2c->readCr($inputFile);
    $j2c->writeJson($outputFile);
} else {
    $j2c->readJson($inputFile);
    $j2c->writeCr($outputFile);
}

fclose($inputFile);
fclose($outputFile);
