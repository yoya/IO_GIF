<?php

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require_once 'IO/Bit.php';
}

class IO_GIF_LZW {
    static function dumpLZWCode($LZWcode, $codeBits, $indicesSize) {
        $bit = new IO_Bit();
        $bit->input($LZWcode);
        $clearCode = pow(2, $codeBits);
        $endCode   = $clearCode + 1;
        $nextCode  = $endCode   + 1;
        $dictionarySize = $clearCode * 2;
        $LZWcodeSize = strlen($LZWcode);
        echo "    CodeBits:$codeBits ClearCode:$clearCode EndCode:$endCode LZWcodeSize:$LZWcodeSize IndicesSize:$indicesSize\n";
        $finish = false;
        $indicesProgress = 0;
        $w = null;
        for ($i = 0; $bit->hasNextData(0); $i++) {
            $code = $bit->getUIBitsLSB($codeBits+1);
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
            $digits = ceil(($codeBits+1)/4);
            printf("    [%d] %0{$digits}X(bits:%d) =>",
                   $i, $code, $codeBits+1);
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
            if (pow(2, $codeBits+1) <= count($dictionaryTable)) {
                $codeBits++;
            }
            if (($indicesProgress >= $indicesSize) || $finish) {
                return ;
            }
        }
    }
    /*
     * from giflib
     * https://sourceforge.net/p/giflib/code/
     */
    const LZ_MAX_CODE  = 4095;
    const NO_SUCH_CODE = 4098;  /* Impossible code, to signal empty. */
    const LZ_BITS      = 12;
    var $_data;
    var $_data_offset;
    function DGifBufferedInput(&$NextByte) {
        $NextByte = ord($this->_data{$this->_data_offset});
        $this->_data_offset++;
    }
    function DGifSetupDecompress($codeBits) {
        $this->BitPerPixel = $codeBits;
        $this->ClearCode = (1 << $this->BitPerPixel);
        $this->EOFCode = $this->ClearCode + 1;
        $this->RunningCode = $this->EOFCode + 1;
        $this->RunningBits = $this->BitPerPixel + 1;
        $this->MaxCode1 = 1 << $this->RunningBits;
        $this->StackPtr = 0;
        $this->LastCode = null;
        $this->CrntShiftState = 0;
        $this->CrntShiftDWord = 0;
        $this->Prefix = new SplFixedArray(self::LZ_MAX_CODE+1);
        for ($i = 0; $i <= self::LZ_MAX_CODE; $i++) {
            $this->Prefix[$i] = self::NO_SUCH_CODE;
        }
    }
    function DGifDecompressInput(&$Code) {
        static $CodeMasks = [ 0x0000, 0x0001, 0x0003, 0x0007,
                              0x000f, 0x001f, 0x003f, 0x007f,
                              0x00ff, 0x01ff, 0x03ff, 0x07ff,
                              0x0fff ];
        if ($this->RunningBits > self::LZ_BITS) {
            throw new Exception("D_GIF_ERR_IMAGE_DEFECT");
        }
        $NextByte = null;
        while ($this->CrntShiftState < $this->RunningBits) {
            $this->DGifBufferedInput($NextByte);
            $this->CrntShiftDWord |= $NextByte << $this->CrntShiftState;
            $this->CrntShiftState += 8;
        }
        $Code = $this->CrntShiftDWord & $CodeMasks[$this->RunningBits];
        $this->CrntShiftDWord >>= $this->RunningBits;
        $this->CrntShiftState -= $this->RunningBits;
        if ($this->RunningCode < self::LZ_MAX_CODE + 2 &&
            ++$this->RunningCode > $this->MaxCode1 &&
            $this->RunningBits < self::LZ_BITS) {
            $this->MaxCode1 <<= 1;
            $this->RunningBits++;
        }
    }
    function dumpLZWCode_Giflib($LZWcode, $codeBits, $indicesSize) {
        // DGifBufferedInput initialize
        $this->_data = $LZWcode;
        $this->_data_offset = 0;
        $this->DGifSetupDecompress($codeBits);
        $Code = null;
        $this->DGifDecompressInput($Code);
        var_dump($Code);
    }
}