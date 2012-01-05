<?php

require_once('IO/GIF.php');

if ($argc < 2) {
    echo "Usage: php gif_dump.php <gif_file>".PHP_EOL;
    exit (1);
}

// $opts = array();
$opts['hexdump'] = true;

$gifdata = file_get_contents($argv[1]);
$gif = new IO_GIF();
$gif->parse($gifdata);
$gif->dump($opts);
