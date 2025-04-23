<?php


/** ENVIRONMENT as it was used in the TeXLive installer **/
/** ONLY Modification:  The ~ has been replaced by  /var/www  which is the home directory of user www-data */


// TODO: not completely clear if we need this and when
// TODO: we not loner use user www-data but rather user apache ????? does this have impacts ??

const TEXDIR          = "/usr/local/texlive/2025";                           // main TeX directory
const TEXMFLOCAL      = "/usr/local/texlive/texmf-local";                    // directory for site-wide local files
const TEXMFSYSVAR     = "/usr/local/texlive/2025/texmf-var";                 // directory for variable and automatically generated data
const TEXMFSYSCONFIG  = "/usr/local/texlive/2025/texmf-config";              // directory for local config
const TEXMFVAR        = "/var/www/.texlive2025/texmf-var";                   // personal directory for variable and automatically generated data
const TEXMFCONFIG     = "/var/www/.texlive2025/texmf-config";                // personal directory for local config
const TEXMFHOME       = "/var/www/texmf";                                    // directory for user-specific files


const TEXINPUTS       = __DIR__."/../local:";                           // directory to search for TeX input files  : is imperative to add the path to the standard classes  
/*** END ENVIRONMENT ***/



?>
