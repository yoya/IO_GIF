IO_GIF
=======

GIF parser &amp; dumper

# Usage

```
% composer require yoya/io_gif
% php vendor/yoya/io_gif/sample/gifdump.php
Usage: php gif_dump.php <gif_file>
% php vendor/yoya/io_gif/sample/gifdump.php -f t.gif
Signature:GIF Version:89a
Screen: Width:2 Height:2
GlobalColorTableFlag:1 ColorResolution:8 SortFlag:0 SizeOfGlobalColorTable: 2
GlobalColorTable:
#000000 #0000ff
BlockLabel:0x21 ExtensionLabel:0xF9 (Graphic Control)
    DisposalMethod:0 UserInputFlag:0
    TransparentColorFlag:1 DelayTime:0 TransparentColorIndex:0
BlockLabel:0x2C (Image)
    Left:0 Top:0 Width:2 Height:2
    LocalColorTableFlag:0 InterlaceFlag:0 SortFlag:0 SizeOfLocalColorTable:0
    ImageData.count:1
BlockLabel:0x3B
```
