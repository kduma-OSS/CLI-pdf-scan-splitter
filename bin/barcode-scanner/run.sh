#!/bin/bash

/usr/bin/pdftoppm -png -r 200 -singlefile -cropbox '/data/input.pdf' '/data/image' || exit 1

/usr/bin/zbarimg -S*.enable  '/data/image.png' > '/data/output.txt' || exit 5
