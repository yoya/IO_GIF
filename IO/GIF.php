<?php

require_once 'IO/Bit.php';

class IO_GIF {
    var $Signature;
    var $Version;
    var $GlobalColorTable = null;
    var $BlockList;
    
    function parse($gifdata) {
        $bit = new IO_Bit();
        $bit->input($gifdata);
        // Header
        $this->Signature = $bit->getData(3);
        $this->Version = $bit->getData(3);
        $this->Screen = array();
        // Logical Screen Descriptor
        $this->Screen['Width'] = $bit->getUI16LE();
        $this->Screen['Height'] = $bit->getUI16LE();
        // <Packed Fields>
        $this->GlobalColorTableFlag = $bit->getUIBit();
        $this->ColorResolution = $bit->getUIBits(3);
        $this->SortFlag = $bit->getUIBit();
        $this->SizeOfGlobalColorTable = $bit->getUIBits(3);
        //
        $this->BackgroundColorIndex = $bit->getUI8();
        $this->PixelAspectRatio = $bit->getUI8();

        // Global Color Table
        if ($this->GlobalColorTableFlag) {
            $globalColorTable = array();
            $colorTableSize = pow(2, $this->SizeOfGlobalColorTable+1);
            for ($i = 0 ; $i < $colorTableSize ; $i++) {
                $rgb = array();
                $rgb[] = $bit->getUI8();
                $rgb[] = $bit->getUI8();
                $rgb[] = $bit->getUI8();
                $globalColorTable[] = $rgb;
            }
            $this->GlobalColorTable = $globalColorTable;
        }
        $this->BlockList = array();
        while (true) {
            list($byte_offset, $dummy) = $bit->getOffset();
            $separator = $bit->getUI8();
            if ($separator === 0x3B) { // Trailer (End of GIF Data Stream)
                break;
            }
            $block = array('BlockLabel' => $separator);
            switch ($separator) {
            case 0x21: // Extension
                $extensionBlockLabel = $bit->getUI8();
                $block['ExtensionLabel'] = $extensionBlockLabel;
                $extensionDataSize = $bit->getUI8();
                $block['ExtensionBlockSize'] = $extensionDataSize;
                if ($extensionDataSize === 0) {
                    break;
                }
                $extensionData = $bit->getData($extensionDataSize);
                $bit_block = new IO_Bit();
                $bit_block->input($extensionData);
                $has_subblock = false;
                switch ($extensionBlockLabel) {
                case 0xF9: // Graphic Control
                    $extensionBlock['Reserved'] = $bit_block->getUIBits(3);
                    $extensionBlock['DisposalMethod'] = $bit_block->getUIBits(3);
                    $extensionBlock['UserInputFlag'] = $bit_block->getUIBit();
                    $extensionBlock['TransparentColorFlag'] = $bit_block->getUIBit();
                    $extensionBlock['DelayTime'] = $bit_block->getUI16LE();
                    $extensionBlock['TransparentColorIndex'] = $bit_block->getUI8();
                    break;
                case 0xFF: // Application Extension
                    $extensionBlock['ApplicationIdentifier'] = $bit_block->getData(8);
                    $extensionBlock['ApplicationAuthenticationCode'] = $bit_block->getData(3);
                    $has_subblock = true;
                    $subblock_label = 'ApplicationData';
                    break;
                case 0xFE: // Comment Extension
                    $extensionBlock['CommentData'] = $extensionData;
                    break;
                default:
                    echo "default($blockLabel)\n";
                    $extensionBlock['Data'] = $extensionData;
                    exit(0);
                    break;
                }
                if ($has_subblock) {
                    $subBlockData = array();
                    while (($subBlockSize = $bit->getUI8()) > 0) {
                        $subBlockData []= $bit->getData($subBlockSize);
                    }
                    $extensionBlock[$subblock_label] = $subBlockData;
                } else {
                    $bit->getUI8(); // $extensionBlockTrailer
                }
                $block['ExtensionData'] = $extensionBlock;
                break;
            case 0x2C: // Image Separator
                $imageDescriptor = array();
                $imageDescriptor['Left'] = $bit->getUI16LE();
                $imageDescriptor['Top'] = $bit->getUI16LE();
                $imageDescriptor['Width'] = $bit->getUI16LE();
                $imageDescriptor['Height'] = $bit->getUI16LE();
                $imageDescriptor['LocalColorTableFlag'] = $bit->getUIBit();
                $imageDescriptor['InterlaceFlag'] = $bit->getUIBit();
                $imageDescriptor['SortFlag'] = $bit->getUIBit();
                $imageDescriptor['Reserved'] = $bit->getUIBits(2);
                $sizeOfLocalColorTable = $bit->getUIBits(3);
                $imageDescriptor['SizeOfLocalColorTable'] = $sizeOfLocalColorTable;
                $block['ImageDescriptor'] = $imageDescriptor;
                if ($imageDescriptor['LocalColorTableFlag']) {
                    $localColorTable = array();
                    $colorTableSize = pow(2, $sizeOfLocalColorTable+1);
                    for ($i = 0 ; $i < $colorTableSize ; $i++) {
                        $rgb = array();
                        $rgb[] = $bit->getUI8();
                        $rgb[] = $bit->getUI8();
                        $rgb[] = $bit->getUI8();
                        $localColorTable[] = $rgb;
                    }
                    $block['LocalColorTable'] = $localColorTable;
                }
                $block['LZWMinimumCodeSize'] = $bit->getUI8();
                $subBlockData = array();
                while (($subBlockSize = $bit->getUI8()) > 0) {
                    $subBlockData []= $bit->getData($subBlockSize);
                }
                $block['ImageData'] = $subBlockData;
                break;
            default:
                echo "what?($separator)\n";
                print_r($bit->getOffset()); echo "\n";
                exit(0);
                break;
            }
            $this->BlockList[] = $block;
        }
    }
    function dump() {
        echo "Signature:{$this->Signature} Version:{$this->Version}\n";
        echo "Screen: Width:{$this->Screen['Width']} Height:{$this->Screen['Height']}\n";
        echo "GlobalColorTableFlag:{$this->GlobalColorTableFlag} ";
        echo "ColorResolution:".($this->ColorResolution + 1)." ";
        echo "SortFlag:{$this->SortFlag} ";
        echo "SizeOfGlobalColorTable: ".pow(2, $this->SizeOfGlobalColorTable+1)."\n";
        if (is_null($this->GlobalColorTable) === false) {
            foreach ($this->GlobalColorTable as $idx => $rgb) {
                printf("#%02x%02x%02x ", $rgb[0], $rgb[1], $rgb[2]);
                if (($idx % 8) == 7) {
                    echo "\n";
                }
            }
            if (($idx % 8) !== 7) {
                echo "\n";
            }
        }
        foreach ($this->BlockList as $block) {
            $blockLabel = $block['BlockLabel'];
            printf("BlockLabel:0x%02X", $blockLabel);
            switch ($blockLabel) {
            case 0x21: // Extension Block
                $extensionLabel = $block['ExtensionLabel'];
                printf(" ExtensionLabel:0x%02X", $extensionLabel);
                $extensionData = $block['ExtensionData'];
                switch ($extensionLabel) {
                case 0xF9: // Graphic Control
                    echo " (Graphic Control)\n";
                    echo "    DisposalMethod:{$extensionData['DisposalMethod']} UserInputFlag:{$extensionData['UserInputFlag']}\n";
                    echo "    TransparentColorFlag:{$extensionData['TransparentColorFlag']} DelayTime:{$extensionData['DelayTime']} TransparentColorIndex:{$extensionData['TransparentColorIndex']}";
                    break;
                case 0xFF: // Application Extension
                    echo " (Application Extension)\n";
                    echo "    ApplicationIdentifier:{$extensionData['ApplicationIdentifier']} ApplicationAuthenticationCode:{$extensionData['ApplicationAuthenticationCode']}\n";
                    echo "    ApplicationData.count:".count($extensionData['ApplicationData']);
                    break;
                case 0xFE: // Comment Extension
                    echo " (Comment Extension)\n";
                    echo "    CommentData:".$extensionData['CommentData'];
                    break;
                }
                break;
            case 0x2C: // Image Separator
                echo " (Image)\n";
                $desc = $block['ImageDescriptor'];
                echo "    Left:{$desc['Left']} Top:{$desc['Top']} Width:{$desc['Width']} Height:{$desc['Height']}\n";
                echo "    LocalColorTableFlag:{$desc['LocalColorTableFlag']} InterlaceFlag:{$desc['InterlaceFlag']} SortFlag:{$desc['SortFlag']} SizeOfLocalColorTable:{$desc['SizeOfLocalColorTable']}\n";
                echo "    ImageData.count:".count($block['ImageData']);
                break;
            }
            echo "\n";
        }
    }
    function build() {
        $bit = new IO_Bit();
        if (isset($this->GlobalColorTable) && (($colorTableSize = count($this->GlobalColorTable)) > 0)) {
            if ($colorTableSize > 256) {
                $colorTableSize = 256; // upper limit number of colors
            }
            if ($colorTableSize < 2) {
                $this->SizeOfGlobalColorTable = 0;
            } else {
                $this->SizeOfGlobalColorTable = ceil(log($colorTableSize, 2)) - 1;
            }
            $this->GlobalColorTableFlag = 1;
        } else {
            $this->GlobalColorTableFlag = 0;
            $this->SizeOfGlobalColorTable = 0;
        }
        // Header
        $bit->putData($this->Signature, 3);
        $bit->putData($this->Version, 3);
        // Logical Screen Descriptor
        $bit->putUI16LE($this->Screen['Width']);
        $bit->putUI16LE($this->Screen['Height']);
        // <Packed Fields>
        $bit->putUIBit($this->GlobalColorTableFlag);
        $bit->putUIBits($this->ColorResolution, 3);
        $bit->putUIBit($this->SortFlag);
        $bit->putUIBits($this->SizeOfGlobalColorTable, 3);
        //
        $bit->putUI8($this->BackgroundColorIndex);
        $bit->putUI8($this->PixelAspectRatio);

        // Global Color Table
        if ($this->GlobalColorTableFlag) {
            $globalColorTable = array();
            $colorTableSize = pow(2, $this->SizeOfGlobalColorTable+1);
            foreach ($this->GlobalColorTable as $rgb) {
                $bit->putUI8($rgb[0]);
                $bit->putUI8($rgb[1]);
                $bit->putUI8($rgb[2]);
            }
        }
        foreach ($this->BlockList as $block) {
            $separator = $block['BlockLabel'];
            $bit->putUI8($separator);
            if ($separator === 0x3B) { // Trailer (End of GIF Data Stream)
                break;
            }
            $has_subblock = false;
            switch ($separator) {
            case 0x21: // Extension
                $extensionBlockLabel = $block['ExtensionLabel'];
                $extensionDataSize = $block['ExtensionBlockSize'];
                $bit->putUI8($extensionBlockLabel);
                $extensionBlock = $block['ExtensionData'];
                $bit_block = new IO_Bit();
                switch ($extensionBlockLabel) {
                case 0xF9: // Graphic Control
                    $bit_block->putUIBits(0, 3); // Reserved
                    $bit_block->putUIBits($extensionBlock['DisposalMethod'], 3);
                    $bit_block->putUIBit($extensionBlock['UserInputFlag']);
                    $bit_block->putUIBit($extensionBlock['TransparentColorFlag']);
                    $bit_block->putUI16LE($extensionBlock['DelayTime']);
                    $bit_block->putUI8($extensionBlock['TransparentColorIndex']);
                    break;
                case 0xFF: // Application Extension
                    $bit_block->putData($extensionBlock['ApplicationIdentifier'], 8);
                    $bit_block->putData($extensionBlock['ApplicationAuthenticationCode'], 3);
                    $has_subblock = true;
                    $subblock_label = 'ApplicationData';
                    break;
                case 0xFE: // Comment Extension
                    $bit_block->putData($extensionBlock['CommentData']);
                    break;
                default:
                    echo "default($blockLabel)\n";
                    $extensionBlock['Data'] = $extensionData;
                    exit(0);
                    break;
                }
                $extensionData = $bit_block->output();
                $extensionDataSize = strlen($extensionData);
                $bit->putUI8($extensionDataSize);
                $bit->putData($extensionData, $extensionDataSize);

                if ($has_subblock) {
                    foreach ($extensionBlock[$subblock_label] as $subBlock) {
                        $subBlockSize = strlen($subBlock);
                        $bit->putUI8($subBlockSize);
                        $bit->putData($subBlock);
                    }
                }
                $bit->putUI8(0); // $extensionBlockTrailer
                break;
            case 0x2C: // Image Separator
                if (isset($block['LocalColorTable']) && (($colorTableSize = count($block['LocalColorTable'])) > 0)) {
                    if ($colorTableSize > 256) {
                        $colorTableSize = 256; // upper limit number of colors
                    }
                    if ($colorTableSize < 2) {
                        $sizeOfLocalColorTable = 0;
                    } else {
                        $sizeOfLocalColorTable = ceil(log($colorTableSize, 2)) - 1;
                    }
                    $imageDescriptor['LocalColorTableFlag'] = 1;
                } else {
                    $imageDescriptor['LocalColorTableFlag'] = 0;
                    $sizeOfLocalColorTable = 0;
                }

                $imageDescriptor = $block['ImageDescriptor'];
                $bit->putUI16LE($imageDescriptor['Left']);
                $bit->putUI16LE($imageDescriptor['Top']);
                $bit->putUI16LE($imageDescriptor['Width']);
                $bit->putUI16LE($imageDescriptor['Height']);
                $bit->putUIBit($imageDescriptor['LocalColorTableFlag']);
                $bit->putUIBit($imageDescriptor['InterlaceFlag']);
                $bit->putUIBit($imageDescriptor['SortFlag']);
                $bit->putUIBits($imageDescriptor['Reserved'], 2);
                $bit->putUIBits($sizeOfLocalColorTable, 3);

                if ($imageDescriptor['LocalColorTableFlag']) {
                    $localColorTable = $block['LocalColorTable'];
                    $colorTableSize = pow(2, $sizeOfLocalColorTable+1);
                    foreach ($localColorTable as $rgb) {
                        $bit->putUI8($rgb[0]);
                        $bit->putUI8($rgb[1]);
                        $bit->putUI8($rgb[2]);
                    }
                }
                $bit->putUI8($block['LZWMinimumCodeSize']);
                foreach ($block['ImageData'] as $subBlockData) {
                    $bit->putUI8(strlen($subBlockData));
                    $bit->putData($subBlockData);
                }
                $bit->putUI8(0); // Sub-block Trailer
                break;
            default:
                echo "what?($separator)\n";
                print_r($bit->getOffset()); echo "\n";
                exit(0);
                break;
            }
        }
        $bit->putUI8(0x3B); // Trailer (End of GIF Data Stream)
        return $bit->output();
    }
}
