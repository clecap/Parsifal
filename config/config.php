<?php

/*** NOTE: This config.php file MUST contain only const declarations and no declarations of global variables 
 *   This is due to a number of issues with PHP design where 1) const cannot be used in string interpolation 
 *   and 2) const definitions are not allowed to contain (most) operations and 3) the peculiar way we neeed this 
 *   file in mediawiki as well as in our own endpoint files.
*/



const LATEX_BINARY      = "latex";
const PDFLATEX_BINARY   = "pdflatex";                                                // path to pdflatex executable;

/** PATH must include normal Linux path (such as sed, uname, mkdir, rm and possibly more) and path to pdflatex */
const PATH              = "/usr/local/bin:/usr/bin:/bin:/bin";

const HOME              = "/var/www";

const TEXINPUTS         = __DIR__."/../local:";                           // directory to search for TeX input files  : is imperative to add the path to the standard classes  


/** THE REMAINING PARTS of this file SHOULD NOT BE MODIFIED unless developing the system further */

/* *** ADDING another xml tag ***

1) Parsifal.php         for the hooks       // TODO: make this more flexible
2) TexProcessor.php     for the mapping     // TODO: make this more flexible

The place where we want to insert the xml tag content:
  magic-line-identifier-for-inclusion

When we use a preloaded format we must use the correct versions(one for latex, one for pdflatex).
We do so by placing the formats into different format directories and use different environment variables.
*/

/* Regular expressions */
// for detecting a line which starts or ends a Latex text portion */
/* ensure that the tags are prefix free (so no tex and textest at the same time */

const BASIC_SIZE = 1000;



const END_PREAMBLE_HOOK   = "magic-line-for-end-preamble-hook";
const BEFORE_CONTENT_HOOK = "magic-line-for-before-content-hook";
const MAGIC_LINE          =  "magic-line-identifier-for-inclusion";                     // this line in the latex template is replaced by the content between the tags
const AFTER_CONTENT_HOOK = "magic-line-for-after-content-hook";

/** Markers for the error parser; exported to JavaScript as window.ERROR_PARSER_* by config.js.php */
const ERROR_PARSER_START = "START-MARKER-TYPED-OUT-FOR-ERROR-PARSER";
const ERROR_PARSER_END   = "END-MARKER-TYPED-OUT-FOR-ERROR-PARSER";

// texmf.cnf settings passed on every TeX command line: one message per log line (no wrapping at column 79) for the error parser, and wide error context; TeX requires error_line < 256 and half_error_line <= error_line - 15
const TEX_LOG_OPTIONS  = " -cnf-line=max_print_line=1000 -cnf-line=error_line=254 -cnf-line=half_error_line=238 ";

const LATEX_COMMAND    =  LATEX_BINARY . TEX_LOG_OPTIONS . "  --interaction=nonstopmode  -file-line-error-style  ";
const PDFLATEX_COMMAND =  PDFLATEX_BINARY . TEX_LOG_OPTIONS . " --interaction=nonstopmode -file-line-error-style ";


/*** System dependent components used in the filesystem path for php ***/
const LATEX_FORMAT_PATH    =  __DIR__."/../formats_latex/";     
const PDFLATEX_FORMAT_PATH =  __DIR__."/../formats_pdflatex/";     
const PY_PATH              =  __DIR__."/../py/";                                             // path to the directory containing python scripts


/*** The path for the cache ***/
/* NOTE: we want this outside of extension/Parsifal since this can grow to a very large number of files and then we will have problems when 
   invoking a directory caching editor on extension/Parsifal */
const CACHE_PATH    =  __DIR__."/../../../parsifal-cache/";                             // path to the temporary cache directory, must end with a slash
$CACHE_PATH         = CACHE_PATH;                                                    // moving this from a const to a $ since PHP disallows consts in string extrapolations
const CACHE_URL     = "/parsifal-cache/";                                            // URL relative to scriptPath, must end with a slash /

/* we must have a directory for parsifal lock files; this directory must be part of a local file system and must not be mounted via nsf (where
  we tested that the lock mechanism does not work reliably). Usually this works in /var/lock
 */
const LOCK_DIR_PATH = "/var/lock/parsifal";

/*** Some Log Paths ***/
const LOG_PATH      =  __DIR__."/../LOGFILE";                                       // path to log file
const ERR_PATH      =  __DIR__."/../ERRORLOG";                                      // path to log file  // TODO: do we actually need this ???


// map collapsible attributes c-* and o-* to specific names, if existing
// do this serverside (i.e. compile time) and not clientside (i.e. run time) for page-control by author 
const ATT2NAME = array ( );

// maps names shown on collapse-buttons of latex collapsibles to the style to be used for them
const ATT2STYLE_SPEC = array (
  "Motivation"       =>   "background-color:pink;",
  "Application"      =>   "background-color:pink;",          "Anwendung"       =>  "background-color:pink;",
  "Proof"            =>   "background-color:yellow;",        "Beweis"          =>  "background-color:yellow;",
  "Notation"         =>   "background-color:salmon;",
  "Example"          =>   "background-color:PaleGreen;",     "Beispiel"        =>  "background-color:PaleGreen;",
  "Counterexample"   =>   "background-color:red;",           "Gegenbeispiel"   =>  "background-color:red;",
  "Remark"           =>   "background-color:lightblue;",     "Bemerkung"       =>  "background-color:lightblue",
  "Note"             =>   "background-color:AliceBlue;",     "Notiz"           =>  "background-color:lightblue",
  "Lemma"            =>   "background-color:AliceBlue;",  
  "Corollary"        =>   "background-color:AliceBlue;",     "Korollar"        =>  "background-color:lightblue"
);

// default styling of the clooapse button of the Parsifal Latex collapsible, if no name registered in ATT2STYLE_SPEC is used
const ATT_DEFAULT_STYLE_SPEC = "background-color:Bisque;";


const ATT_DEFAULT_NAME = "Info";


/*** Components which may be addressed via URL */
const ENDPOINT_URL       = "/extensions/Parsifal/endpoints/tex-preview.php";           // endpoint for the preview
const ENDPOINT_REFRESH   = "/extensions/Parsifal/endpoints/tex-refresh.php";           // endpoint for the preview
const HTML_URL           = "/extensions/Parsifal/html/";                               // path to the html directory containing texLog.html and others

?>