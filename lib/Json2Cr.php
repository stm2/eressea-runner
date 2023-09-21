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
            echo "Invalid json input\n";
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
}

$scriptname = $_SERVER['PHP_SELF'];
if ($argc > 2 && $argv[1] == '-c') {
    $scriptname = $argv[2];
    $argv = array_slice($argv, 2);
    $argc -= 2;
}
$scriptname = basename($scriptname);

if ($argc > 1 && $argv[1] == '-h' || ($argc != 3 && $argc != 1)) {
    echo "usage: $scriptname [-h] [<input.json> <output.cr>]\n";
    exit(0);
}

if ($argc == 3) {
    $inputFilename = $argv[1];
    $outputFilename = $argv[2];


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

$j2c->readJson($inputFile);
$j2c->writeCr($outputFile);


fclose($inputFile);
fclose($outputFile);
