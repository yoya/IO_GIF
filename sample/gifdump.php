<?php

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require_once('IO/GIF.php');
}

function usage() {
    echo "Usage: php gifdump.php -f <gif_file> [-h] [-z]".PHP_EOL;
}

$options = getopt("f:hz");

if (isset($options['f']) === false) {
    usage();
    exit (1);
}
$filename = $options['f'];

$opts = array();
$opts['hexdump'] = isset($options['h']);
$opts['lzwcode']  = isset($options['z']);

$gifdata = file_get_contents($filename);
$gif = new IO_GIF();
$gif->parse($gifdata);
$gif->dump($opts);
