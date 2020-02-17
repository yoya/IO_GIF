<?php

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require_once 'IO/Bit.php';
}

class IO_GIF_LZW {
    static function dumpLZWCode($LZWcode, $LZWCodeSize, $indicesSize) {
        $bit = new IO_Bit();
        $bit->input($LZWcode);
        $clearCode = pow(2, $LZWCodeSize);
        $endCode   = $clearCode + 1;
        $nextCode  = $endCode   + 1;
        $dictionarySize = $clearCode * 2;
        echo "LZWCodeSize:$LZWCodeSize clearCode:$clearCode endCode:$endCode\n";
        $finish = false;
        $indicesProgress = 0;
        $w = null;
        for ($i = 0; $bit->hasNextData(0); $i++) {
            $code = $bit->getUIBitsLSB($LZWCodeSize+1);
            if ($code === $clearCode) {
                // echo "=====  ClearCode\n";
                $dictionaryTable = [];
                for ($j = 0; $j < $clearCode; $j++) {
                    $dictionaryTable [] = [$j];
                }
                $dictionaryTable [] = null; // ClearCode
                $dictionaryTable [] = null; // EndCode
                $output = [];
            } else if ($code === $endCode) {
                // echo "===== EndCode\n";
                $finish = true;
                $output = [];
            } else {
                if (isset($dictionaryTable[$code])) {
                    $output = $dictionaryTable[$code];
                } else {
                    $output = array_merge($w, [$w[0]]);
                }
                if (is_array($w)) {
                    $dictionaryTable []= array_merge($w, [$output[0]]);
                }
                $w = $output;
            }
            $digits = ceil(($LZWCodeSize+1)/4);
            printf("    [%d] %0{$digits}X(bits:%d) =>",
                   $i, $code, $LZWCodeSize+1);
            if ($code === $clearCode) {
                echo " <clearCode>\n";
            } else if ($code === $endCode) {
                echo " <endCode>\n";
            } else {
                echo " [$indicesProgress]";
                foreach ($output as $c) {
                    printf(" %02X", $c);
                }
                echo "\n";
                $indicesProgress += count($output);
            }
            if (pow(2, $LZWCodeSize+1) <= count($dictionaryTable)) {
                $LZWCodeSize++;
            }
            if (($indicesProgress >= $indicesSize) || $finish) {
                return ;
            }
        }
    }
}
