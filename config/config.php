<?php

/*** NOTE: This config.php file MUST contain only const declarations and no declarations of global variables 
 *   This is due to a number of issues with PHP design where 1) const cannot be used in string interpolation 
 *   and 2) const definitions are not allowed to contain (most) operations and 3) the peculiar way we neeed this 
 *   file in mediawiki as well as in our own endpoint files.
*/

require_once ("config-paths.php");  // load the path settings required for this operating system scenario (MUST ADJUST this file!)
require_once ("config-env.php");    // load the environment settings required by TeXLive (MIGHT NEED TO ADJUST this file!)

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

// TODO: deprecated. delete after a while
// const TAG_REGEX_START_STRING = "<amsmath|<large:amsmath|<pre-amsmath|<tex|<pdftex"; 
// const TAG_REGEX_END_STRING   = "<\/amsmath>|<\/large:amsmath>|<\/pre-amsmath>|<\/tex>|<\/tex>";


const BASIC_SIZE = 1000;


const TAGS = array("amsmath", "tex", "beamer");  // string array of tag names which are handled by the tex generator



const END_PREAMBLE_HOOK   = "magic-line-for-end-preamble-hook";
const BEFORE_CONTENT_HOOK = "magic-line-for-before-content-hook";

const MAGIC_LINE          =  "magic-line-identifier-for-inclusion";                     // this line in the latex template is replaced by the content between the tags

const AFTER_CONTENT_HOOK = "magic-line-for-after-content-hook";

/** IMPORTANT: MUST HAVE SAME VALUE AS IN CONFIG.JS */
const ERROR_PARSER_START = "START-MARKER-TYPED-OUT-FOR-ERROR-PARSER";
const ERROR_PARSER_END   = "END-MARKER-TYPED-OUT-FOR-ERROR-PARSER";


// minipage is used to hold the page width stable   // TODO: cave: as beamer demonstrates: there seems to be no effect of the wiodth - why ???


// THIS we can deprecate
// const MEASURE = "\\newwrite\\yposoutputfile\\openout\\yposoutputfile=\\jobname.ypos\\pdfsavepos\\write\\yposoutputfile{\\the\\pagetotal}";





const LATEX_COMMAND    =  LATEX_BINARY . "  --interaction=nonstopmode  -file-line-error-style  "; 
const PDFLATEX_COMMAND =  PDFLATEX_BINARY . " --interaction=nonstopmode -file-line-error-style ";

const NODE_SCRIPT      = __DIR__."/../annos/offline.mjs";                            // path to the offline.mjs script
const PDF2HTML         = NODE_BINARY." ".__DIR__."/../annos/annoHtml.js ";

/*** System dependent components used in the filesystem path for php ***/
const TEMPLATE_PATH        =  __DIR__."/../template/";                 // path to the template directory, relative to the installation path $IP  must end with a slash
const LATEX_FORMAT_PATH    =  __DIR__."/../formats_latex/";     
const PDFLATEX_FORMAT_PATH =  __DIR__."/../formats_pdflatex/";     
const JS_PATH              =  __DIR__."/../js/";                                             // path to the directory containing javascript
const PY_PATH              =  __DIR__."/../py/";                                             // path to the directory containing python scripts


/*** The path for the cache ***/
/* NOTE: we want this outside of extension/Parsifal since this can grow to a very large number of files and then we will have problems when 
   invoking a directory caching editor on extension/Parsifal */
const CACHE_PATH    =  __DIR__."/../../../parsifal-cache/";                             // path to the temporary cache directory, must end with a slash
$CACHE_PATH         = CACHE_PATH;                                                    // moving this from a const to a $ since PHP disallows consts in string extrapolations
const CACHE_URL     = "/parsifal-cache/";                                            // URL relative to scriptPath, must end with a slash /


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
const TEMPLATE_URL       = "/extensions/Parsifal/template/";
const ENDPOINT_URL       = "/extensions/Parsifal/endpoints/tex-preview.php";           // endpoint for the preview
const ENDPOINT_REFRESH   = "/extensions/Parsifal/endpoints/tex-refresh.php";           // endpoint for the preview
const HTML_URL           = "/extensions/Parsifal/html/";                               // path to the html directory containing texLog.html and others




?>