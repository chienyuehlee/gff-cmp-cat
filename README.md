#gff-cmp-cat#

https://github.com/chienyuehlee/gff-cmp-cat

## Introduction ##

Gff-cmp-cat is a web-based tool to 1) validate contents of a gff3 file and 2) calculate the differences between overlapping genes and mRNAs between two gff3 files.

1) During the validation step of the gff3 file, gff-cmp-cat returns a detailed report of the file and provides several quality check methods, which include:

- Redundant features: Flags features with identical values from column 1 to 8.
- Minus coordinates: Flags features with negative coordinates. 
- Zero start: Flags features with a start coordinate of 0.
- Incomplete: Identifies gene features without any child features (e.g. mRNA, exon, CDS). 
- Coordinate boundary: Checks whether a child feature’s coordinates exceed those of its parent feature.
- Redundant gene length: Checks whether a parent gene actually has child mRNA features that comprise the entire length of the gene. 
- mRNA in pseudogene: Flags mRNA features that have a pseudogene parent.


2) During the comparison step, the first file is considered the ‘original’ file, and the second file contains manual curations of the first file. Once loaded, the differences between overlapping genes and mRNAs from the first file and the second file are calculated. The differences are categorized into eight ‘action’ types. Here, we describe these actions for the mRNA feature type:

- ‘Added Models’:  A curated mRNA is considered ‘added’ if there is no overlap with a predicted mRNA.
- ‘Extended Models’ and ‘Reduced Models’: A curated mRNA model is extended or reduced if 1) it only overlaps with one predicted mRNA, and 2) the curated model is longer or shorter, respectively. 
- ‘Models modified within boundary coordinates’: Some overlapping models may have the same length between the two files, but still have changed sequence; for these, the ‘internally modified’ function checks whether 1) the start and end coordinates of two overlapping models are the same, and 2) if any changes in the component exons or CDS segments have occurred. 
- ‘Merged Models (CDS)’: A curated mRNA is defined as ‘merged’ if its CDS overlaps with CDS segments from two or more predicted mRNAs. 
- ‘Merged Models (UTR)’: Instead of CDS, exon(s) from a curated mRNA overlap with exons from two or more predicted mRNAs; at least one of the mRNAs must only have UTR overlap. 
- ‘Split Models (CDS)’: If CDS from two or more curated mRNAs overlap CDS from one predicted mRNA, and if the curated mRNAs are not only isoforms of one model, then these models are considered ‘split’.
- ‘Split Models (UTR)’: Instead of CDS, exons from two or more curated models overlap with exon(s) from a predicted mRNA; at least one of the mRNAs must only have UTR overlap; and the predicted mRNAs are not only isoforms of one model.



## Installation ##

### Prerequisites ###
- php v5.3 or above
- zlib v1.2 or above

### Running on localhost ###
1. Make sure a web server is running in your system, and then go to the directory which the web server can access (e.g., /home/YOUR\_ID/public\_html).
2. Use the command `git clone https://github.com/chienyuehlee/gff-cmp-cat` to retrieve a copy of the complete repository.
3. Open a browser and type the appropriate URL (e.g., http://127.0.0.1/~YOUR_ID/gff-cmp-cat) to run gff-cmp-cat.


## Usage ##

There are three main steps to analyze gff3 files in this tool:

1. Choose two gff3 files and upload via a browser on the uploading tab.
2. Click the ‘Check GFF’ button to validate each gff3 file. Validation results will show on the checking results tab. If any error is detected, please fix it and upload again. 
3. Specify which uploaded file is the original or curated file, and then press the ‘Run gff-cmp-cat’ button to start the comparison analysis. Comparison results will show on the gff-cmp-cat results tab. In addition, the detailed results of each action type archived a zip file can be downloaded by clicking the ‘Detailed results download’ button on the upper left side.


## Demo ##

[Click here](http://www.sakura.idv.tw/~kinomoto/gff-cmp-cat)
