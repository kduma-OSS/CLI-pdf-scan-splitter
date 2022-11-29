#!/bin/bash
/usr/bin/pdfseparate '/data/input.pdf' '/data/output-%d.pdf' || exit 1
