# CLI-pdf-scan-splitter

Scanned PDF files splitter and sorter based on barcode

### `pss` tool

This tool reads every PDF provided as an argument, splits every page into separate PDF and looks for a Code-128 barcode on each page. 
If a barcode is found, the page is saved into a file named after the barcode into the output directory. 
If no barcode is found, the page is saved into a file named `UNKNOWN.pdf`.
If there are multiple barcodes on a page, the page is saved into a file named after first barcode.
If there is multiple pages with the same barcode, the pages are saved into a file with a number suffix for duplicates.

**WARNING: Files already existing in the output directory will be overwritten!**

```./pss process <output_dir> <pdf> [<pdf>...]```
