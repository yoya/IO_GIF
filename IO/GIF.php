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
        ;
    }
}

// test routine

$gifdata = file_get_contents($argv[1]);
$gif = new IO_GIF();
$gif->parse($gifdata);

$gif->dump();

