<?php

/** 
*/
 

/**
 * The TeXCompilationMode controls how we compose the tex file to be compiled from the <source>
 * especially regarding the manner how precompiled preambles are added
      PC_LATEX      combine with a precompiled version of the template to be used in the latex processor
      PC_PDFLATEX   combine with a precompiled version of the template to be used in the pdflatex processor
      LATEX         combine with a template but without assuming precompilation, to be used with latex processor
      PDFLATEX      combine with a template but without assuming precompilation, to be used with pdflatex processor
      RAW           do not combine with any template at all, use the raw user input
 * 
 */
enum TeXCompilationMode: string {
  case PC_LATEX      = 'pc_latex';
  case PC_PDFLATEX   = 'pc_pdflatex';
  case LATEX         = 'latex';
  case PDFLATEX      = 'pdflatex';
  case RAW           = 'raw';

}