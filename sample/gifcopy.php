<?php

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require_once('IO/GIF.php');
}

if ($argc < 2) {
    echo "Usage: php gif_copy.php <gif_file>".PHP_EOL;
    exit (1);
}

$gifdata = file_get_contents($argv[1]);
$gif = new IO_GIF();
$gif->parse($gifdata);
echo $gif->build();
