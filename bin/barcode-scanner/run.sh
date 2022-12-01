#!/bin/bash

/usr/bin/pdftoppm -png -r $1 -singlefile -cropbox '/data/input.pdf' '/data/image' || exit 1

/usr/bin/zbarimg --set '*.disable' --set '*.no-position' --set '*.x-density=2' --set '*.y-density=2' --set 'code128.enable' --set 'qrcode.enable'  '/data/image.png' > '/data/output.txt' || exit 5
