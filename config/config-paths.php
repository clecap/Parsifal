<?php

/*** NOTE: config-paths.php must contain the paths under which specific programs can be found by PHP
 *   
 *   This file MUST be adjusted by the user, unless a standard configuration is used.
 */


const GHOSTSCRIPT       = "/usr/bin/gs";                                             // path to the ghostscript executable: determine by which gs
const MUTOOL            = "/usr/bin/mutool";                                         // path to the mutool executable:      determine by which mutool

const NODE_BINARY       = "/usr/local/bin/node";                                     // path to the node executable:        determine by which node
// NOTE: we currently are deorecating the use of node.  TODO
//       as we use mutools instead - but we still keep the sources in


## TODO: deprecate this maybe - we are not using this anyhow
const DVIPNG_BINARY     = "/usr/local/texlive/2025/bin/x86_64-linux/dvipng";     // path to the dvipng executable:      determine by which dvipng

const LATEX_BINARY      = "latex";
const PDFLATEX_BINARY   = "pdflatex";                                                // path to pdflatex executable;

/** PATH must include normal Linux path (such as sed, uname, mkdir, rm and possibly more) and path to pdflatex */
const PATH     = "/usr/local/bin:/usr/bin:/bin:/bin:/usr/local/texlive/2025/bin/x86_64-linux";


const HOME        = "/var/www";


?>