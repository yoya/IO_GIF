<?php

require_once('IO/GIF.php');

function usage() {
    echo "Usage: php gif_dump.php <gif_file>".PHP_EOL;
}

$options = getopt("f:h");

if (isset($options['f']) === false) {
    usage();
    exit (1);
}
$filename = $options['f'];

$opts = array();
if (isset($options['h'])) {
    $opts['hexdump'] = true;
}

$gifdata = file_get_contents($filename);
$gif = new IO_GIF();
$gif->parse($gifdata);
$gif->dump($opts);
