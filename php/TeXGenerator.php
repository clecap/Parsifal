<?php



/** TeXGenerator bundles all functions for transforming Wiki contents into Latex source */

class TeXGenerator {


/** generate stuff to be placed into the preamble (before begin{document}) but at the end of the preamble
 *  this is of particular importance for those parts of the preamble which cannot be precompiled or which we want to add dynamically
 */

public static function generateEndPreambleStuff ($ar, $tag) {
#region
  global $wgServer, $wgScriptPath;
  $stuff = "";

  $stuff = $stuff."\\def\\dantePrefix{"."/var/www/html/".$wgScriptPath."}";

  // SANS:  array key   "sans"  turns the default font into a sans serif font
  if ( array_key_exists ( "sans", $ar ) ) { $stuff = $stuff."\\renewcommand{\\familydefault}{\\sfdefault}"; }

  // LOCALIZATION: array keys  de, nde, babel, en   and  default: english
  if      ( array_key_exists ( "de",    $ar ) )  { $stuff = $stuff."\\usepackage[shorthands=off,german]{babel}";            }
  else if ( array_key_exists ( "nde",   $ar ) )  { $stuff = $stuff."\\usepackage[shorthands=off,ngerman]{babel}";           }
  else if ( array_key_exists ( "babel", $ar ) )  { $stuff = $stuff."\\usepackage[shorthands=off,".$ar["babel"]."]{babel}";  }
  else if ( array_key_exists ( "en",    $ar ) )  { $stuff = $stuff."\\usepackage[shorthands=off,english]{babel}";           }
  else                                           { $stuff = $stuff."\\usepackage[shorthands=off,english]{babel}";           }

  // MINTED
  $light = array ("manny", "rrt", "perldoc", "borland", "colorful", "murphy", "vs", "trac", "tango", "autumn", "bw", "emacs", "pastie", "friendly");
  $dark  = array ( "fruity", "vim", "native", "monokai");
  $mintedStyle = "emacs";
  // array key   "minted"  if present: adds package minted and uses style emacs
  //                       if present and has a value: uses that value as style for minted, provided the style is known

  if ( array_key_exists ("minted", $ar) ) {                                                       // if we have a minted attribute, include minted stuff
  //    if ( in_array ($ar["minted"], $light) ) { $style=$ar["minted"]; } else { $style = "emacs";}   // check if style is known. If it is not, use emacs as default
  ##### $stuff = $stuff . "\\usepackage[outputdir=".CACHE_PATH.",newfloat=true,cache]{minted}\\usemintedstyle{" .$style. "}\\initializeMinted"; 
    if (isset($ar['minted']) && is_string($ar['minted']) && trim($ar['minted']) !== '') { $mintedStyle = $ar['minted']; }

    $mintedInit = "";

     if ( array_key_exists ("minted-linenos", $ar) ) { $mintedInit .= "\\initMintedLinenos";}  else {}
     if ( array_key_exists ("minted-box",     $ar) ) { $mintedInit .= "\\initMintedBox";}       else {}

    $stuff = $stuff . "\\usepackage[outputdir=".CACHE_PATH.",newfloat=true,cache]{minted}\\usemintedstyle{".$mintedStyle."}".$mintedInit; 

  }

  // PREAMBLE
  // array key    "pa" if present and has contents
  //                   if contents starts with [[  and ends with ]] then use the string in between as reference to a Mediawiki parsifal template file
  $addPreamble = "";
  if ( array_key_exists ("pa", $ar) ) {
    if ( str_starts_with ( $ar["pa"], "[[") &&  str_ends_with ( $ar["pa"], "]]" ) ) {
      $name = substr  ($ar["pa"], 2, -2 );
      $configPage = "ParsifalMacro/$name";                                                            
      $title      = Title::newFromText( $configPage, NS_MEDIAWIKI );                            
      if ($title == null) { throw new Exception ("no template file $name found for Parsifal");}                                                         // signal the caller that we did not find a configuration page for this portlet
      $wikipage   = new WikiPage ($title);                                                        // get the WikiPage for that title
      if ($wikipage == null) { throw new Exception ("no wikipage $name found for Parsifal");}                                                      // signal the caller that we did not get a WikiPage
      $contentObject = $wikipage->getContent();                                                   // and obtain the content object for that

      if ($contentObject) { $contentText = ContentHandler::getContentText( $contentObject );     $addPreamble = extractPreContents ($contentText); }
      else { $addPreamble = "NO CONTENTOBJECT PREAMBLE FILE $name";}
    }   
  else { $addPreamble = $ar["pa"];}
  }

  // add some further stuff into the preamble
  $optional = <<<EOD
\\usepackage{environ}%         Needed for some additional definitions
\\usepackage{ocg-p}%

\\newcounter{optionals}
\\NewEnviron{opt}[1]{
  \\stepcounter{optionals}
  \\begin{ocg}{#1}{oxc\\theoptionals}{0}%  Argument is name.  Body is content. It is initially not visible.
  \\BODY%
  \\end{ocg}%
}
\\NewEnviron{OPT}[1]{%    capitalized: initially visible
  \\stepcounter{optionals}
  \\begin{ocg}{#1}{oxc\\theoptionals}{1}%  Argument is name.  Body is content. It is initially visible.
  \\BODY%
  \\end{ocg}%
}
EOD;

  // dynamically inject the definitions of \dref and of \durl, since we here (in PHP) have the required paths accessible more easily than from LaTeX
  $urlStuff  = "\\newcommand{\dref}[2]{ \\StrSubstitute{#1}{ }{_}[\\temp]\\href{".   $wgServer.$wgScriptPath . "/index.php/"."\\temp}{#2}}";
  $urlStuff2 = "\\newcommand{\durl}[1]{ \\StrSubstitute{#1}{ }{_}[\\temp]\\href{".   $wgServer.$wgScriptPath . "/index.php/"."\\temp}{#1}}";

  $urlStuff  = "\\newcommand{\dref}[2]{ \\StrSubstitute{#1}{ }{_}[\\temp]\\href{".   $wgServer.$wgScriptPath . "/index.php?title="."\\temp}{#2}}";
  $urlStuff2 = "\\newcommand{\durl}[1]{ \\StrSubstitute{#1}{ }{_}[\\temp]\\href{".   $wgServer.$wgScriptPath . "/index.php?title="."\\temp}{#1}}";


  return $stuff . $urlStuff. $urlStuff2 . $optional . $addPreamble;
#endregion
}






/* generate stuff to be placed after the preamble (ie. this starts with begin{document})
 * and which prepares the template substitution process
 */
public static function generateBeforeContentStuff ($ar, $tag) {
#region
  $stuff    = "";
  $variants = "";
  
  // implement size related attributes
  $size="15cm";
  if ( array_key_exists ( "wide", $ar ) ) { $size="25cm";}
  if ( array_key_exists ( "slim", $ar ) ) { $size="5cm";}
  if ( array_key_exists ( "width", $ar) ) { $size=$ar["width"];}

  $margin="0cm";
  if ( array_key_exists ( "margin", $ar) ) { $margin=$ar["margin"];}

  $config = "\\standaloneconfig{margin=".$margin."}"; // need to override the standalone documentclass option of the template

  // implement variant related properties
  if ( array_key_exists ( "number-of-instance", $ar ) ) { 
    $variants = "\\gdef\\numberOfInstance{" . $ar["number-of-instance"] . "}";
  } else { 
    $variants = "\\gdef\\numberOfInstance{0}";
  }

  # CAVE: confusion between tex { } and php { } should be avoided !
  // minipage is used to hold the page width stable. without it we also get some indentation artifacts with enumitem.

  switch ($tag) {
    case "amsmath":   $stuff = $config."\\begin{document}"."\\begin{minipage}[]{".$size."}\\myInitialize\\relax ".MAGIC_LINE."\\end{minipage}\\end{document}"; break;
    case "tex":       $stuff = MAGIC_LINE; break;
    case "beamer":    $stuff = "\\begin{document} ".MAGIC_LINE."\\end{document}"; break;
  }
  $stuff = $variants . $stuff;
  return $stuff;
  #endregion
}





#region generateTex
/** determine hash code of content and if no TeX file is there, build one
 *    $rawContent          string containing latex content
 *    $tag                 string with tagname of xml tag /
 *    $mode                the mode tag, i.e.  "" or "pc_latex" or "pc_pdflatex"  which controls how we inject the raw content into the template or precompilation
 *    $ar                  array of key => value form with the attributes
 *    $cookedContent       if  null   do not copy cooked content into the variable, only write it into a file
 *                         if  FILE   copy cooked content into the variable AND write it into a file
 *                         if  VAR    copy cooked content into the variable
 *    $fc                  if true:   add a comment requesting the specific precompiled format file
 *                            false:  do not add comment, assume that we are using a command line format specification or not format at all 
 */
public static function generateTex ( string $rawContent, string $tag, string $mode, $ar = array(), string &$cookedContent = null, $fc = true, $parserPreamble="" ) : string {
  $VERBOSE               = false;
  $CACHE_PATH            = CACHE_PATH;
  $TEMPLATE_PATH         = TEMPLATE_PATH;  
  $LATEX_FORMAT_PATH     = LATEX_FORMAT_PATH;  
  $PDFLATEX_FORMAT_PATH  = PDFLATEX_FORMAT_PATH;

//  $rawContent            = self::modifyTex ( $rawContent, $tag, $mode, $ar);

  if ($VERBOSE) {self::debugLog ("generateTex: attribute array is: ".print_r ($ar, true). "\n");}
  ksort($ar);                                             // sort array on keys, in place, so that the hash becomes independent on the sequence 
  $stringAr = print_r ($ar, true);                        // go from php array to a full string representation
  $hash     = md5 ($tag.$stringAr.$rawContent);           // derive a unique file name - need dependency on tag, content and attributes as all of this has impact on looks.


// TODO: CAVE: we should not do the format reconstruction here in this place. It should be part of a startup process of the entire call
//       because we could otherwise get race conditions on multiple runs !
// TODO: CAVE: maybe this is done dynamically so we cannot !


  switch ($mode) {
    case "pc_latex":           // we use a precompilation made for the latex processor     
      $fmt="$LATEX_FORMAT_PATH$tag";     
      $resultFile = "$CACHE_PATH{$hash}_$mode.tex";  
      if (!file_exists ("$fmt.fmt")) { Parsifal::reconstructFormat ("ParsifalTemplate/$tag");}
      ASSERT_FILE ("$fmt.fmt");    
   
      $endPreambleStuff   = TeXGenerator::generateEndPreambleStuff ($ar, $tag) . $parserPreamble;
      $beforeContentStuff = TeXGenerator::generateBeforeContentStuff ($ar, $tag);
      $template =  ($fc ? "%&$fmt\n" : "").$endPreambleStuff.$beforeContentStuff;    
      break;

    case "pc_pdflatex":        // we use a precompilation made for the pdflatex processor
      $fmt="$PDFLATEX_FORMAT_PATH$tag";  $resultFile = "$CACHE_PATH{$hash}_$mode.tex";  
      if (!file_exists ("$fmt.fmt")) { Parsifal::reconstructFormat ("ParsifalTemplate/$tag");}
      ASSERT_FILE ("$fmt.fmt");    
      $endPreambleStuff   = "%%% generateEndPreambleStuff\n " . TeXGenerator::generateEndPreambleStuff ($ar, $tag) . $parserPreamble;
      $beforeContentStuff = "%%% generateBeforecontentStuff\n " . TeXGenerator::generateBeforeContentStuff ($ar, $tag);
//      $template = "\\documentclass{standalone}". TeXGenerator::generateBeforeContentStuff ($ar, $tag);
//      $template =  ($fc ? "%&$fmt\n" : "%%%NOSO%%%").$endPreambleStuff.$beforeContentStuff;   
      $template =   "%&$fmt\n" .$endPreambleStuff.$beforeContentStuff;   
      break;

    case "": 
      $resultFile       = "$CACHE_PATH{$hash}$mode.tex";                                      // only in THIS case no underscore - above we NEED underscore
      $templateFileName = "$TEMPLATE_PATH$tag.tex";  
      ASSERT_FILE ($templateFileName);
      $template         = file_get_contents ($templateFileName) . $endPreambleStuff;   // TODO: WHO does the begin document now ???
      break;
    default: 
      throw new Exception ("generateTex: received illegal mode $mode");
  }

  if (strpos ($template, MAGIC_LINE) == FALSE)   {
    $msg = "generateTex: could not find MAGIC_LINE in template file " . $templateFileName . "\nFor more information see logfile at " .LOG_PATH. "\n";
    self::debugLog ($msg);
    self::debugLog ("---------Template:\n" .$template. "\n-----END-----\nMAGIC_LINE IS:\n\n" . MAGIC_LINE . "\n\n");
    throw new Exception ($msg); }  // check for MAGIC_LINE in template
  $markerStart = "\\typeout{" . ERROR_PARSER_START . "}";  // form a marker which helps the error parser in the log file detect the beginning of the document
  $markerEnd   = "\\typeout{" . ERROR_PARSER_END   . "}";  // form a marker which helps the error parser in the log file detect the beginning of the document


  $text = str_replace ( MAGIC_LINE, $markerStart.$rawContent.$markerEnd, $template);     // replace the MAGIC_LINE by the current input; maximally one replacement

  switch ($cookedContent) {
    case "FILE":   if ( $fileObject = fopen( $resultFile, 'w') ) { fwrite($fileObject, $text);  fclose($fileObject); } else { throw new Exception ("generateTex: error writing result file $resultFile " ); }; 
    case "VAR":    $cookedContent = $text; break;
    case null:     {if ( $fileObject = fopen( $resultFile, 'w') ) { fwrite($fileObject, $text);  fclose($fileObject); } else { throw new Exception ("generateTex: error writing result file $resultFile " ); }  break;}
    default:       throw new Exception ("generateTex: cookedContent has illegal value $cookedContent");
  }

  return $hash;
}
#endregion





}