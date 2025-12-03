<?php

require_once (__DIR__."/../config/config.php");
require_once ("polyfill.php");                       // include some PHP polyfill stuff
require_once ("Decorator.php");

class TeXProcessor {

/** purges the parser cache of a page with given title */  // TODO: deprecated ??
/*
private static function purgeByTitle ($titleText) {
  $title   = Title::newFromText($titleText);
  $article = new Article($parentTitle);
  $article->mTitle->invalidateCache();
}
*/

/** Generate Latex and Pdflatex precompiled versions of the file $name and place them into the respective format directories. */
public static function precompile ($name) {
  $VERBOSE = false;
  $TEMPLATE_PATH = TEMPLATE_PATH; $LATEX_FORMAT_PATH = LATEX_FORMAT_PATH; $PDFLATEX_FORMAT_PATH = PDFLATEX_FORMAT_PATH;
  
  self::ensureEnvironment();
  if ($VERBOSE) {self::debugLog ("TeXProcessor::precompile: Tex2Pdf sees the following environment via getenv(): \n".print_r (getenv(), true) );}
  if ($VERBOSE) {
    self::debugLog ("\n TeXProcessor::precompile shel exec dumping environment sees: \n"); 
    exec ( "env >> ".LOG_PATH );
    self::debugLog ("\n --- DONE --- \n");  
  }

  self::cleanUpAll();  // clean up ALL existing /tmp files since all pages have to be re-done
  
  // clear the format files to ensure that we do not continue to use an old format when a compilation fails and produces no new format file
  if (file_exists ("$LATEX_FORMAT_PATH/$name.fmt"))    { unlink ("$LATEX_FORMAT_PATH/$name.fmt");      }
  if (file_exists ("$LATEX_FORMAT_PATH/$name.fls"))    { unlink ("$LATEX_FORMAT_PATH/$name.fls");      }
  if (file_exists ("$LATEX_FORMAT_PATH/$name.log"))    { unlink ("$LATEX_FORMAT_PATH/$name.log");      }
  if (file_exists ("$PDFLATEX_FORMAT_PATH/$name.fmt")) { unlink ("$PDFLATEX_FORMAT_PATH/$name.fmt");   }
  if (file_exists ("$PDFLATEX_FORMAT_PATH/$name.fls")) { unlink ("$PDFLATEX_FORMAT_PATH/$name.fls");   }
  if (file_exists ("$PDFLATEX_FORMAT_PATH/$name.log")) { unlink ("$PDFLATEX_FORMAT_PATH/$name.log");   }
  
  $cmd1 = "latex  --interaction=nonstopmode  -file-line-error-style  -ini -recorder -output-directory=$LATEX_FORMAT_PATH \"&latex $LATEX_FORMAT_PATH/$name.tex\dump\" "; 
  if ($VERBOSE) { self::debugLog("TeXProcessor::precompile: will now execute the following latex precompile command: \n  ".$cmd1."\n"); }  
  $output1 = null; $retVal1 = null;  
  $retVal1 = TeXProcessor::executor ($cmd1, $output1, $error1, true, $duration1);

  if ($VERBOSE) { self::debugLog("TeXProcessor::precompile: latex command returned: $retVal1 and output: " . print_r ($output1, true)); }
  
  $cmd2 = "pdflatex  --interaction=nonstopmode  -file-line-error-style  -ini -recorder -output-directory=$PDFLATEX_FORMAT_PATH \"&pdflatex $PDFLATEX_FORMAT_PATH/$name.tex\dump\" ";    
  if ($VERBOSE) { self::debugLog("TeXProcessor::precompile: will now execute pdflatex precompile command ".$cmd2."\n"); }
  $output2 = null; $retVal2 = null; 
  $retVal1 = TeXProcessor::executor ($cmd2, $output2, $error2, true, $duration2);
  if ($VERBOSE) {self::debugLog("TeXProcessor::precompile: pdflatex command returned: $retVal2 and output: " . print_r ($output2, true)); } 
  if ($retVal1 != 0 || $retVal2 != 0) {
    return "\nCommand1 was: $cmd1\nRetVal1 was $retVal1\nOutput1 was " . print_r($output1, true) . "\n" . print_r ($error1, true) . "\n\n". 
           "\nCommand2 was: $cmd2\nRetVal1 was $retVal2\nOutput1 was " . print_r($output2, true);
  }
  else {return false;}
}




/** generate stuff to be placed into the preamble (before begin{document}) but at the end of the preamble
 *  this is of particular importance for those parts of the preamble which cannot be precompiled or which we want to add dynamically
 */

private static function generateEndPreambleStuff ($ar, $tag) {
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
private static function generateBeforeContentStuff ($ar, $tag) {
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



private static function getRemainingContent( $parsedData, $amsContent ) {
    // Simple logic to find content after the AMS tag
    $pos = strpos( $parsedData, $amsContent );
    if ( $pos !== false ) {
      return substr( $parsedData, $pos + strlen( $amsContent ) );
    }
    return '';
  }



public static function renderPreamble ($in, $ar, $parser, $frame) {
  $parser->storePreamble=$in;
  return "";
}




public static function lazyRender ($in, $ar, $tag, $parser, $frame) {
  global $wgServer, $wgScriptPath, $wgOut; 
  global $wgAllowVerbose;

  $USE_APCU_CACHE          = true;
  $CACHE_PATH              = CACHE_PATH;
  $VERBOSE                 = true && $wgAllowVerbose;   // $VERBOSE = false && $wgAllowVerbose;
  $VERBOSE_APCU_CACHE      = false;                     // write APCU_CACHE hit and miss info into the log file
  $VERBOSE_APCU_CACHE_FULL = false;                     // write APCU_CACHE details on every cache entry into the log file - CAVE: immense amount of log information

  $pipeMode    = false;  // if true: operate this in pipe mode   if false: operate this in file system mode  // TODO TODO !!!!!
  $startTime   = microtime(true);

  $texSource   = ( $pipeMode ? "FILE" : null);  


  $parserPreamble = "";
  if (property_exists($parser, 'storePreamble')) { $parserPreamble = $parser->storePreamble;}
  // self::debugLog ("STOREDPREAMBLE: " . $parserPreamble. "\n"); 

  $hash       = self::generateTex ($in, $tag, "pc_pdflatex", $ar, $texSource, false, $parserPreamble);      // generate file $hash_pc_pdflatex.tex and return a hash of raw LaTeX source located in Mediawiki

  if ($USE_APCU_CACHE) {  //   APCU_CACHE:  the result of lazyRender might be cached in APCU, the key is the $hash of the TeX source
    $cRet = apcu_fetch ( $hash, $cFlag );
    if ($cFlag) {
      $endTime = microtime (true); $totalDuration = $endTime - $startTime;  
      if ($VERBOSE_APCU_CACHE)      { self::debugLog ("lazyRender: cache HIT for $hash, function call returning after $totalDuration \n");  }
      if ($VERBOSE_APCU_CACHE_FULL) { self::debugLog ( "  Cache info is: " .print_r(apcu_cache_info(), true) ."\n\n");                      }
      return $cRet;  } 
    else { 
      if ($VERBOSE_APCU_CACHE)      { self::debugLog ("lazyRender: cache MISS for $hash \n");                                               }
      if ($VERBOSE_APCU_CACHE_FULL) { self::debugLog ( "  Cache info is: " .print_r(apcu_cache_info(), true) ."\n\n");                      }    }
  }

  // set some paths
  $texPath        = $CACHE_PATH.$hash."_pc_pdflatex.tex"; 
  $annotationPath = $CACHE_PATH.$hash."_pc_pdflatex_final_3.html";                         // the local php file path under which we should find the annotations in form of a (partial) html file  // TODO: hardcoded resolution is bad
  $finalImgPath   = $CACHE_PATH.$hash."_pc_pdflatex_final_3.png";   // TODO: cave hardcoded resolution is bad
  $errorPath      = "$wgScriptPath/extensions/Parsifal/html/texLog.html?"."$wgServer$wgScriptPath".CACHE_URL.$hash."_pc_pdflatex";
  $mrkFileName    = $CACHE_PATH . $hash. "_pc_pdflatex.mrk";
  $lockFileName   = "/var/lock/parsifal/$hash";  // lock the hash, since multiple invocations may induce race conditions (we had that case) 
  // CAVE: lock in /var/lock, since this is not on the mounted volume (where locks do not work) but natively in the container (where locks work)

  $lockStream = fopen ($lockFileName, 'c' );   // create the file
  if ( !$lockStream ) { throw new Exception ("Could not open lock file $lockFileName. This should not happen. ");}

  $ret = "<!-- DEFAULT VALUE just for declaration inside of php, should be overwritten -->";

  if (flock ($lockStream, LOCK_EX ) ) {  // self::debugLog ("lazyRender obtained a lock on $lockFileName at " .microtime(true). "\n");
    try {  // BEGIN EXCEPTION PROTECTED AREA

      $property = $parser->getOutput()->getPageProperty ("Parsifals");        // page should get a property indicating the number of parsifal controlled areas
      $parser->getOutput()->setPageProperty ("Parsifals", ( is_numeric ($property) ? $property+1: 1 ));

      $timestamp = date_format( new DateTime(), 'd-m-Y H:i:s');               // want a timestamp in the img tag on when the page was translated for debugging purposes
      self::ensureEnvironment ();

      // throw new Exception ("THIS is a test for exception handling");  // just for testing exception handling

      /** LATEX to PDF production step **/
      //$timePDF = microtime ();
      $softError = "";
      if ($VERBOSE) {self::debugLog ("lazyRender LATEX2PDF phase for $hash... ") ;}
      if ( !file_exists ($CACHE_PATH . $hash . "_pc_pdflatex.pdf" ) ) {                        //  *** CASE 1: PDF file does not exist: make PDF and pick up error status from function
        if ($VERBOSE) {self::debugLog ( "lazyRender: CASE 1: did not find file " . $CACHE_PATH . $hash . "_pc_pdflatex.pdf, starting TeX2PDF processing for hash= " . $hash. "\n");}
        $softError = ( $pipeMode ? self::Tex2PdfPiped ($texSource, $hash, "_pc_pdflatex") : self::Tex2Pdf ($hash, "_pc_pdflatex", "lazyrender") );
        if ($VERBOSE) {self::debugLog ( "TeX2PDF processing for hash=$hash returned error status: ($softError) \n" );}
        if ( !file_exists ($CACHE_PATH . $hash . "_pc_pdflatex.pdf" ) ) {
          if ($VERBOSE) {self::debugLog ( "After TeX2PDF processing for hash=$hash but cannot find a PDF file\n" );}
          if ( strlen ($softError) == 0) { $softError = "Transient Latex error - could not produce PDF file\n";} // if condition is required to not overwrite existing latex error info with this
        }
        else { if ($VERBOSE) {self::debugLog ( "After TeX2PDF processing for hash=$hash I found the PDF file\n" );} }
      }
      else {                                                                                   //  *** CASE 2: PDF file exists already: pick up error status from error marker file
        if ($VERBOSE) {self::debugLog ( "lazyRender: CASE 2: found PDF file for $hash on disc, picking up old error status from marker file \n" ); }
        // still need to pick up error information from the last run, since the error might not have been fixed by the user, so we still must display it
        $softError = file_get_contents ( $mrkFileName );
        if ( ( $softError = file_get_contents ( $mrkFileName ) ) === false) { throw new ErrorException ("lazyRender: Could not find error marker file " . $CACHE_PATH . $hash. "_pc_pdflatex.mrk"); }

/* // DEPRECATE
        $fileSize    = filesize ( $mrkFileName );
        if      ( $fileSize === false ) { throw new ErrorException ("lazyRender: Could not find error marker file " . $CACHE_PATH . $hash. "_pc_pdflatex.mrk"); }
        else if ( $fileSize > 0 )       { $softError = file_get_contents ($mrkFileName); }
        else         { $softError = ""; }
*/

      }
      //$timePDF = microtime () - $timePDF; self::debugLog ("lazyRender: LATEX2PDF phase took $timePDF [sec] \n");

      /** PDF to PNG and HTML production step  **/
      // We need a width and height in the img tag to assist the browser to a more smooth and flicker-less reflow.
      // The width MUST be equal to the width of the image (or else the browser must rescale the image, which BLURS the image and takes TIME)
      
      $imgExists = file_exists ($finalImgPath);  $annoExists = file_exists ($annotationPath);
      if ( !$imgExists || !$annoExists ) {  // FILES do not both exist
        if ($VERBOSE) { self::debugLog ("lazyRender: missing ". ($imgExists ? " " : $finalImgPath ) . " " . ($annoExists ? " " : $annotationPath ). " calling the processor...\n"); }

        $baseScale = 3;   // TODO: ALLOW to set some basic scale somewhere in or by or for the ParsifalTemplate !!!! itself - so we do nto need to add it in the (in every) tag.

       // attribute scale  
       if ( array_key_exists ("scale",$ar)) {
          $tagScale = floatval($ar["scale"]);
            if ( is_float ($tagScale) ) { $baseScale = 15/$tagScale;   }
        }

        $pdfscale = self::SCALE(BASIC_SIZE, $baseScale);
        if ( array_key_exists ("pdfscale",$ar)) { $pdfscale = floatval($ar["pdfscale"]); }

        if ($VERBOSE) {self::debugLog ("Pdf2PngHtmlMT for $hash starting\n");}
        $timePNG = microtime (true);
        self::Pdf2PngHtmlMT ($hash, $pdfscale, "_pc_pdflatex", "_pc_pdflatex_final_3", $width, $height, $duration );  // 15 
        $timePNG = microtime (true) - $timePNG; 
        if ($VERBOSE) {self::debugLog ("Pdf2PngHtmlMT for $hash completed in $timePNG \n"); }                            // what about an error status here ???????? TODO
      }
      else {         // FILES DO both exist, but we have to pick up width and heght of the image
        if ($VERBOSE) {self::debugLog ("lazyRender: both files (png and annotations) found on disc, no need to call processor\n");} 
        if (file_exists ( $finalImgPath ) ) {
           clearstatcache ( true, $finalImgPath );  // looks like htis is necessary to ensure getimagesize gets the correct answer all the time
          $ims = @getimagesize ( $finalImgPath ); 
          if ($ims) {$width = $ims[0]; $height = $ims[1];} else {
            $imgFileSize = filesize ( $finalImgPath );
            throw new ErrorException ("Looks like $finalImgPath is not yet ready. File size reports it as $imgFileSize ");}   } 
        else {  
          $width=200; $height=200; 
          return "Currently we have no image for display. It is possible that the LaTeX source did not produce any output. Missing file is $finalImgPath";  // TODO
        }
        self::debugLog ("lazyRender: size found $width $height");
      }

      /** BUILD IMG TAG **/
      $naming = ( array_key_exists ("n", $ar) ? "data-name='".$ar["n"]."' " : "");             // prepare a data-name attribute for the image

      // image tag style
      $style = "style=\"";
      $markingClass = "";
      if ( array_key_exists ("number-of-instance", $ar) ) { $style .= "margin:20px;";   // TODO: usage !!
        $markingClass = "instance_".$ar["number-of-instance"];
      }

      if ( array_key_exists ("b", $ar) )  { $style .= "border:1px solid gold;";            }     // add a border
      if ( array_key_exists ("br", $ar) ) { $style .= "border-radius:5px;";                }     // add a border radius
      if ( array_key_exists ("bs", $ar) ) { $style .= "box-shadow: 10px 10px lightgrey;";  }     // add a box shadow
      if ( array_key_exists ("style", $ar) )  { $style .= $ar["style"];            }             // add custom style for the img tag

      $style .= "width:100%; vertical-align: baseline; display:none;\"";  // vertical-align:baseline: the page around the image flickers a bit when parsifal runtime makes them visible with showImage - this prevents it

      $titleInfo = "";  // currently unused 
      $dataHash  = "data-hash=\"".$hash."\"";                   // attribute helpful for debugging and maybe more
      $onShow    = "onload=\"this.style.display='block';\"";    // function which turns off image and only turns on after completed load; protects user from seeing half-loaded images, which DOES happen for longer texts

      $srcImg = 'src="'.$wgServer.$wgScriptPath.CACHE_URL.$hash."_pc_pdflatex_final_3.png".'"'; 

      // TODO: identical contents leads to identical hashes leads to two elements with the same id, which is made
      //       we are / should be migrating this to using $dataHash only !
      $imgTag      = "<img $naming id=\"$hash\" $dataHash  data-timestamp='$timestamp'  $style $onShow class='texImage' alt='Image is being processed, please wait, will fault it in automatically as soon as it is readyss' $srcImg ></img>";

      $annotations  = (file_exists ($annotationPath) ? file_get_contents ($annotationPath) :  null );   // get annotations, if no file present, use null

      /** ADD decorations */
      $core = new Decorator ( $imgTag, $width, $height, $markingClass);
      $core->wrap ( $annotations, $softError, $errorPath, $titleInfo, $hash);      // wrap with annotations and error information   

      $core->collapsible ( $ar );                                                  // decorate with collapsibles
      $ret = $core->getHTML ();                                                    // generate HTML which includes the decorations

    } // try

    // in case of exception, build a suitable error element for return
    catch (\Exception $e) { $msg=$e->getMessage(); $stk=$e->getTraceAsString(); self::debugLog ("lanzRenderer: Exception: $e \n$msg\n$stk\n\n");  $ret = "<b>$msg</b><br>$stk<br>";} 
    catch (\Throwable $e) { $msg=$e->getMessage(); $stk=$e->getTraceAsString(); self::debugLog ("lazyRenderer: Thworable: $e \n$msg\n$stk\n\n");  $ret = "<b>$msg</b><br>$stk<br>";}
    finally               { fclose ($lockStream); }  // self::debugLog ("lazyrender returned a lock for $hash at ".microtime(true) . " \n");

    if ($USE_APCU_CACHE) {
      $cRet = apcu_store ($hash, $ret, 1000);  // cache the result we just generated so we have a faster access to the html generated and do not have to regenerate this from the files; lives 1000 seconds
      if ($VERBOSE_APCU_CACHE)      { self::debugLog ("lazyRenderer: Wrote result for $hash into APCU cache, result was $cRet \n"); }
      if ($VERBOSE_APCU_CACHE_FULL) { self::debugLog ( "  Cache info is: " .print_r(apcu_cache_info(), true) ."\n\n");              }
    }

  } // end if (flock) 
  else { self::debugLog ("lazyRenderer: Could not obtain lock for $hash \n"); }  // NOTE: lock files are removed by regular crontab script

  if (true || $VERBOSE) {$endTime = microtime (true); $totalDuration = $endTime - $startTime;  self::debugLog ("lazyRender for $hash completed in $totalDuration [sec] \n\n");}

  return $ret;
}











// given a $hash, returns an html object tag
// disadvantage: this produces an entire player frame, which we do not want: DO NOT USE
public static function objectPdf ($hash, $width, $height) {
  global $wgServer, $wgScriptPath;
  $style = "";
  $url   = $wgServer.$wgScriptPath.CACHE_URL.$hash."_pc_pdflatex.pdf";
  $html = "<object width='".$width."' height='".$height."' style='max-width: 100%;' data='".$url."' type='application/pdf'></object>";
  return $html;
}


// given a $hash, returns html code with a rendering canvas
// disadvantage: looks like pdfjslib is not properly reentrant and the parallel calls this may caus for several canvases could lead to issues
// they show up when we have several canvases on the same html pag
public static function canvasPdf ($hash, $width, $height) {
  global $wgServer, $wgScriptPath;
  $url = $wgServer.$wgScriptPath.CACHE_URL.$hash."_pc_pdflatex.pdf";
 // $canvas = "<canvas width='".$width."' height='".$height."' style='max-width: 100%; border:2px solid red;' data='".$url."'  id='canvas-".$hash."'  ></canvase>";
 $canvas = "<canvas  style='max-width: 100%; border:2px solid red;' data='".$url."'  id='canvas-".$hash."'  ></canvas>";

  $js     = "<script>PRT.renderPDF ('".$url."','".$hash."');</script>";
  $html = $canvas . $js;
  return $html;
}

// given a $hash, return html code with an iframe rendering this inside of the iframe s
public static function iframePdf ($hash, $width, $height, $scale, $titleInfo) {
  global $wgServer, $wgScriptPath;
  $basis  = $wgServer.$wgScriptPath."/extensions/Parsifal/html/pdfIframe.html";    // path to the html page generating canvas 
  $url    = $wgServer.$wgScriptPath.CACHE_URL.$hash."_pc_pdflatex.pdf";
  $urlSearch = "url=".urlencode ($url);
  $urlScale  = "scale=".urlencode ($scale);
  $urlInfo   = "info=true";
  $urlHash   = "hash=".urlencode ($hash);
  $iframeUrl = $basis."?".$urlSearch."&".$urlScale."&".$urlInfo."&".$urlHash;

//  $style = "max-width:100%;" . "width:".$width."px; height:".$height."px; border:1px solid green;";

  $width += 172;  // 62

  $style = "max-width:100%;" . "width:".$width."px; height:".$height."; border:1px solid red; overflow:hidden; ";

//  $style = "max-width:100%; width:100%; border:1px solid green;";

  $infoLine = "<div>TexProcessor.php: Infoline: iframe is: width=$width  height=$height</div>";

  $html = $infoLine."<iframe   scrolling='no'     style='".$style."' src='".$iframeUrl."'  id='iframe-".$hash."' title='".$hash."' ></iframe>";
  return $html;
}

// render by producing a call to Parsifal Runtime.
// advantage: client can decide, how to render a page
public static function jsRender ($hash, $width, $height, $scale, $titleInfo) {
  global $wgServer, $wgScriptPath;
  return "<script> PRT.jsRender(\"$hash\", $width, \"$height\", $scale, \"$titleInfo\", \"$wgServer\", \"$wgScriptPath\", \""  .CACHE_URL. "\");</script>";
}



  // NOTE: TeXProcessor::renderPreviewPNG and _base64 and called functions can be called from the web and from mediawiki and hence must not depend on any mediawiki global variables - all configuration done in config.php


/* SCALING CALCULATION: Given the number of pixels we have available in the presentation IMG, what is the required DPI value to be used in divpng?
   
   We get 591 pixel width for 100 dpi for a document of Latex width 15cm
   15cm = 5,90551 inches  in resolution 100 dpi we get 590,551 dots and with rounding 591 as width 
     
   Pixels = dpi * LatexWidthInCm * cmToInches  
   dpi = availablePixelWidth / (LatexWidthInCm * cmToInches) = 2.54 * availablePixelWidth / LatexWidthInCm

   We have $pixels many pixels available in the HTML area of our image.
   The text document is 
*/
public static function DPI ($pixels, $textWidthCm) { return floor (2.54 * $pixels / $textWidthCm); }


/* Scaling calculation: Given the number of pixels we have available in the presentation IMG, what is the required scale value to be used in pdf.js rendering?
    
  We get 1079 pixel width when we compile a 15cm width Latex document with a scale of 2.54.
  We get 424  pixel width when we compile a 15cm width Latex document with a scale of 1.00
  
  In scale = 1.00 we get: Per cm Latex: 28 pixel.  Per inch Latex: 72 pixel 
  
  LatexWidthInCm [cm] of  latex are  LatexWidthInCm * cmToInches [inches] are LatexWidthInCm * cmToInches * 72 [pixel] = LatexWidthInCm *72 / 2.54  [pixels]

  scale = 2.54 * pixels / ( textWidthCm * 72 )
*/
public static function SCALE ($pixels, $textWidthCm) {return (2.54 * $pixels) / (1.0 * $textWidthCm * 72);}



/* ensures that PHP has the right concept of a processing environment, as required for TeX  */
private static function ensureEnvironment () {
  $VERBOSE  = false;
  $ALREADY  = "already";

  // $start = hrtime (true);  // only for development - time this, results see below

  $parsifalenvironment = getenv('PARSIFALENVIRONMENT');
  if ( strcmp ($parsifalenvironment, $ALREADY) == 0 ) {
    if ($VERBOSE) {self::debugLog ("TeXProcessor::ensureEnvironment: environment has already been setup - exiting \n");}
    return;
  }

  if ($VERBOSE) {self::debugLog ("TeXProcessor::ensureEnvironment before the environment patching sees the following environment: \n".print_r (getenv(), true) );}  
  if ($VERBOSE) {self::debugLog ("TeXProcessor::ensureEnvironment patches TEXDIR=".TEXDIR."\n" );}  
  if ($VERBOSE) {self::debugLog ("TeXProcessor::ensureEnvironment patches PATH=".PATH."\n" );}  

  // set the environment variables as specified in the TeXLive installer
  $flag = true;
  $flag = $flag and putenv ("PARSIFALENVIRONMENT=already");
  $flag = $flag and putenv ("TEXDIR=".TEXDIR); 
  $flag = $flag and putenv ("TEXMFLOCAL=".TEXMFLOCAL);
  $flag = $flag and putenv ("TEXMFSYSVAR=".TEXMFSYSVAR); 
  $flag = $flag and putenv ("TEXMFSYSCONFIG=".TEXMFSYSCONFIG); 
  $flag = $flag and putenv ("TEXMFVAR=".TEXMFVAR);
  $flag = $flag and putenv ("TEXMFCONFIG=".TEXMFCONFIG);    
  $flag = $flag and putenv ("TEXMFHOME=".TEXMFHOME);      

  $flag = $flag and putenv ("max_print_line=1000");      
  $flag = $flag and putenv ("error_line=254");      
  $flag = $flag and putenv ("half_error_line=238");      

  // need PATH to tex binaries and to standard OS binaries such as sed and others for font generation scripts
  $flag = $flag and putenv ("PATH=".PATH);
  $flag = $flag and putenv ("HOME=".HOME);
  
  // want to access local style and packges files from a local directory
  $flag = $flag and putenv ("TEXINPUTS=".TEXINPUTS);

  if ($flag === false) {throw new Exception ("Parsifal was unable to putenv environment. Must check if putenv is enabled in php.ini or similar!");}

  if ($VERBOSE) {self::debugLog ("TeXProcessor::ensureEnvironment: putting PATH to env returned: ".print_r ( putenv("PATH=".PATH) . "\n", true) );}  
  if ($VERBOSE) {self::debugLog ("TeXProcessor::ensureEnvironment: getting PATH from env returned: ".print_r ( getenv("PATH") . "\n", true) );}  
  if ($VERBOSE) {self::debugLog ("TeXProcessor::ensureEnvironment: after environment patching sees the following environment: \n".print_r (getenv(), true) );}  

  //$duration = hrtime(true)-$start; self::debugLog ("TeXProcessor::ensureEnvironment duration: $duration \n");  // negligent. some 20 micro seconds
}


/* ensure existence of a disc cache directory */
public static function ensureCacheDirectory () {
  $CACHE_PATH = CACHE_PATH;
  if ( !file_exists ($CACHE_PATH) ) {
    self::debugLog( "TeXProcessor::ensureCacheDirectory: detected a missing cache directory; trying to construct: $CACHE_PATH \n");     
    $retVal = mkdir ($CACHE_PATH, 0755);  
    self::debugLog( "  mkdir returned $retVal \n");
  }
}


// return exception and error information; use as error handler in all client render methods; provides text, recipient decides on wrapping html etc.
// $add: additional info,    $ex: exception thrown   $nameBase additional info which hash etc was affected
static function renderError ($add, $ex, $nameBase) {
  $txt = $ex->getMessage()."\nhash=$nameBase\n".$ex->getTraceAsString();  
  header ("X-Latex-Hash:".$nameBase); 
  header ("X-Parsifal-Error:Hard"); 
  header ("Content-type:text/html"); 
  header ("Content-Length: " .strlen( $txt) );
  echo $txt;
  self::debugLog ("renderError called: " . $add. "  " . $ex->getMessage() . "\n");
}  
  
// clean up all files belonging to a specific hash
static function cleanUp ($hash) {
  $CACHE_PATH = CACHE_PATH;     
  unlink ( "$CACHE_PATH$hash_pc_pdflatex.tex");  
  unlink ( "$CACHE_PATH$hash_pc_pdflatex.aux");    
  unlink ( "$CACHE_PATH$hash_pc_pdflatex.aux");    
  unlink ( "$CACHE_PATH$hash_pc_pdflatex.log");   
  unlink ( "$CACHE_PATH$hash_pc_pdflatex.out");   
  unlink ( "$CACHE_PATH$hash_pc_pdflatex.png");   
  unlink ( "$CACHE_PATH$hash_pc_pdflatex.pdf");   
  unlink ( "$CACHE_PATH$hash_pc_pdflatex.html");  
  unlink ( "$CACHE_PATH$hash_pc_pdflatex.mrk");                  
}
   
  
// clean up all files in $CACHE_PATH, independently of the hash
static function cleanUpAll () {
  $CACHE_PATH = CACHE_PATH;
  $VERBOSE = true;
  if ($VERBOSE) {self::debugLog ("cleanUpAll called \n");}
  $files = glob ( $CACHE_PATH."*_pc_pdflatex.tex");  $count = count ( $files ); if ($VERBOSE) {self::debugLog ("found $count _pc_pdflatex.tex files for deletion \n");}  array_map ("unlink", $files);
  $files = glob ( $CACHE_PATH."*_pc_pdflatex.aux");  $count = count ( $files ); if ($VERBOSE) {self::debugLog ("found $count _pc_pdflatex.aux files for deletion \n");}  array_map ("unlink", $files);  
  $files = glob ( $CACHE_PATH."*_pc_pdflatex.log");  $count = count ( $files ); if ($VERBOSE) {self::debugLog ("found $count _pc_pdflatex.log files for deletion \n");}  array_map ("unlink", $files);  
  $files = glob ( $CACHE_PATH."*_pc_pdflatex.out");  $count = count ( $files ); if ($VERBOSE) {self::debugLog ("found $count _pc_pdflatex.out files for deletion \n");}  array_map ("unlink", $files);  
  $files = glob ( $CACHE_PATH."*_pc_pdflatex.png");  $count = count ( $files ); if ($VERBOSE) {self::debugLog ("found $count _pc_pdflatex.png files for deletion \n");}  array_map ("unlink", $files);  
  $files = glob ( $CACHE_PATH."*_pc_pdflatex.pdf");  $count = count ( $files ); if ($VERBOSE) {self::debugLog ("found $count _pc_pdflatex.pdf files for deletion \n");}  array_map ("unlink", $files);  
  $files = glob ( $CACHE_PATH."*_pc_pdflatex.html"); $count = count ( $files ); if ($VERBOSE) {self::debugLog ("found $count _pc_pdflatex.html files for deletion \n");}  array_map ("unlink", $files);  
  $files = glob ( $CACHE_PATH."*_pc_pdflatex.mrk");  $count = count ( $files ); if ($VERBOSE) {self::debugLog ("found $count _pc_pdflatex.mrk files for deletion \n");}  array_map ("unlink", $files);              
}
  


  static function debugLog ($text) {
    if($tmpFile = fopen( LOG_PATH, 'a')) {fwrite($tmpFile, $text); fclose($tmpFile); }  // NOTE: close immediatley after writing to ensure proper flush
    else {throw new Exception ("debugLog in TexProcessor could not log"); }
  }
  
  
  static function errorLog ($text) {
    if($tmpFile = fopen( ERR_PATH, 'a')) {fwrite($tmpFile, $text);  fclose($tmpFile); } 
    else {throw new Exception ("errorLog in TexProcessor could not log"); }
  }  


/* TODO DEPRECATE
// TODO: missing
private static function ensureFmtFile ($fmt) {$articleName = "MediaWiki:ParsifalTemplate/$fmt";}
*/



// modify raw tex input for various purposes
private static function modifyTex ( string $rawContent, string $tag, string $mode, $ar = array() ) : string {

  $callback = function ($matches) {
//    self::debugLog ( "\n\n Callback found " . count($matches) . " matches \n");
    foreach ($matches as $value) {}
  };

 $newContent =  preg_replace_callback ("/\\dref\{([^{}]*)\}/", $callback, $rawContent);
  
//  self::debugLog ( "\n\n New contents. " . $newContent . " \n------------------\n\n");
  return $newContent;
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
private static function generateTex ( string $rawContent, string $tag, string $mode, $ar = array(), string &$cookedContent = null, $fc = true, $parserPreamble="" ) : string {
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
   
      $endPreambleStuff   = self::generateEndPreambleStuff ($ar, $tag) . $parserPreamble;
      $beforeContentStuff = self::generateBeforeContentStuff ($ar, $tag);
      $template =  ($fc ? "%&$fmt\n" : "").$endPreambleStuff.$beforeContentStuff;    
      break;

    case "pc_pdflatex":        // we use a precompilation made for the pdflatex processor
      $fmt="$PDFLATEX_FORMAT_PATH$tag";  $resultFile = "$CACHE_PATH{$hash}_$mode.tex";  
      if (!file_exists ("$fmt.fmt")) { Parsifal::reconstructFormat ("ParsifalTemplate/$tag");}
      ASSERT_FILE ("$fmt.fmt");    
      $endPreambleStuff   = "%%% generateEndPreambleStuff\n " . self::generateEndPreambleStuff ($ar, $tag) . $parserPreamble;
      $beforeContentStuff = "%%% generateBeforecontentStuff\n " . self::generateBeforeContentStuff ($ar, $tag);
//      $template = "\\documentclass{standalone}". self::generateBeforeContentStuff ($ar, $tag);
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



/** GENERATE PNG from PDF via mutool. Transforms $hash.pdf into $hash$final.png
 */
private static function Pdf2PngMT ($hash, $dpi, $inFinal, $outFinal) {
  $VERBOSE = false;  
  $CACHE_PATH = CACHE_PATH;
  $cmd = MUTOOL. " convert -O resolution=$dpi -o $CACHE_PATH$hash$outFinal.png $CACHE_PATH$hash$inFinal.pdf  1-1";     // 1-1 is the page range    
  if ($VERBOSE) {$startTime = microtime(true);  self::debugLog ("Pdf2PngMT started for $hash, command is: " . $cmd . "\n");}
  // $output = null;  $retVal = null;  //  exec ( $cmd, $output, $retVal );  
  exec ( $cmd );  
  if ($VERBOSE) {$endTime = microtime (true); $duration = $endTime - $startTime; self::debugLog ("  completed Pdf2PngMT. DURATION: " . $duration . "\n"); }  
  // if ($VERBOSE) {self::debugLog ("  return value $retVal  output ". print_r ($output, true));} 
}


// CURRENTLY USED 
/** GENERATE PNG from PDF. Transforms $hash$inFinal.pdf into $hash$inFinal.png */
private static function Pdf2PngHtmlMT ($hash, $scale, $inFinal, $outFinal, &$width, &$height, &$duration = null) {
  $VERBOSE = false; 
  $JS_PATH = JS_PATH;  $CACHE_PATH = CACHE_PATH;  $PY_PATH = PY_PATH;

  $cmd = "$PY_PATH/make.py $scale $CACHE_PATH$hash$inFinal $CACHE_PATH$hash$outFinal ";
  if ($VERBOSE)  { self::debugLog ("\n TeXProcessor::Pdf2PngHtmlMT $hash starting: \n"); }
  $retVal = TeXProcessor::executor ($cmd, $output, $error, false, $duration);

  $values = explode(' ', $output);
  list($width, $height) = sscanf($output, "%f %f");

  if ($VERBOSE)  { 
    self::debugLog ("\n\nPdf2PngHtmlMT: pymupdf output for $hash is:\n".$output. "\n"); 
    self::debugLog (    "Pdf2PngHtmlMT: pymupdf return for $hash is:\n".$retVal. "\n"); 
    self::debugLog (    "Pdf2PngHtmlMT: pymupdf error  for $hash is:\n".$error.  "\n\n");
  }
  return $retVal;
}





/** GENERATE PNG from PDF. Transforms $hash$inFinal.pdf into $hash$inFinal.png */
/*
private static function Pdf2PngHtmlMT_MUT ($hash, $scale, $inFinal, $outFinal, &$duration = null) {
  $VERBOSE = true; 
  $JS_PATH = JS_PATH;  $CACHE_PATH = CACHE_PATH;  $PY_PATH = PY_PATH;

  $cmd = MUTOOL. " run  $JS_PATH/my-device.js $scale $CACHE_PATH$hash$inFinal $CACHE_PATH$hash$outFinal ";      // COMMAND:   /usr/bin/mutool  run 

 if ($VERBOSE)  { self::debugLog ("\n TeXProcessor:: executor $hash starting: \n"); }
  $retVal = TeXProcessor::executor ($cmd, $output, $error, false, $duration);
 if ($VERBOSE)  { self::debugLog ("\n TeXProcessor:: executor $hash finished: \n"); }
  
  //if ($VERBOSE)  { self::debugLog ("\n TeXProcessor:: mutool execution for scale=$scale hash=$hash had a duration of: ".$duration . "\n"); }
  //if ($VERBOSE)  { self::debugLog ("\n TeXProcessor:: mutool run output $hash shellexecutor: \n".$output); }
  // TODO: better error handling
}

*/



/** GENERATE PNG from PDF via mutool. Transforms $hash$inFinal.pdf into $hash$inFinal.png */
private static function Pdf2PngHtmlMT_BG ($hash, $scale, $inFinal, $outFinal, &$duration = null) {
  $VERBOSE = true; $JS_PATH = JS_PATH;  $CACHE_PATH = CACHE_PATH;  $PY_PATH = PY_PATH;
  $cmd = MUTOOL. " run  $JS_PATH/my-device.js $scale $CACHE_PATH$hash$inFinal $CACHE_PATH$hash$outFinal ";      // COMMAND:   /usr/bin/mutool  run  
  // $output = null;  $retVal = null;  $error = null;
  //$retVal = TeXProcessor::executor ($cmd, $output, $error, true, $duration);
  // TODO: error handling
  exec ( $cmd . " > /dev/null 2>&1 & " );
}


// CURRENTLY NOT UISED
// this is the variant returning the html / svg / annotation code directly
private static function Pdf2PngHtmlMT_RET ($hash, $scale, $inFinal, $outFinal, &$duration = null) {
  $VERBOSE = true; 
  $JS_PATH = JS_PATH;  $CACHE_PATH = CACHE_PATH;  $PY_PATH = PY_PATH;

//  $cmd = MUTOOL. " run  $JS_PATH/my-device.js $scale $CACHE_PATH$hash$inFinal $CACHE_PATH$hash$outFinal ";      // COMMAND:   /usr/bin/mutool  run 

  $cmd = "$PY_PATH/make2.py $scale $CACHE_PATH$hash$inFinal $CACHE_PATH$hash$outFinal "; 
 if ($VERBOSE)  { self::debugLog ("\n TeXProcessor:: executor $hash starting: \n"); }
  $retVal = TeXProcessor::executor ($cmd, $output, $error, false, $duration);
 if ($VERBOSE)  { self::debugLog ("\n TeXProcessor:: executor $hash finished: \n"); }
  
  if ($VERBOSE)  { self::debugLog ("\n TeXProcessor:: mutool execution for scale=$scale hash=$hash had a duration of: ".$duration . "\n"); }
  if ($VERBOSE)  { self::debugLog ("\n TeXProcessor:: mutool run output $hash shellexecutor: \n".$output); }
  // TODO: better error handling


  return $output;
}















private static function manuBoth ( $hash1, $inFinal1, $note1, $hash2, $scale2, $inFinal2, $outFinal2) {
  $VERBOSE = true;  $CACHE_PATH = CACHE_PATH;  $JS_PATH = JS_PATH;  
  ASSERT_FILE ("$CACHE_PATH$hash1$inFinal1.tex"); 
  // if ($VERBOSE) {self::debugLog ("Tex2Pdf sees the following environment: \n".print_r (getenv(), true) );}  // uncomment to check the active environment
  $cmd1 = "pdflatex  --interaction=nonstopmode  -file-line-error-style -output-directory=$CACHE_PATH $CACHE_PATH$hash1$inFinal1.tex  >/dev/null 2>&1  "; 
  $cmd2 = MUTOOL. " run  $JS_PATH/my-device.js $scale2 $CACHE_PATH$hash2$inFinal2 $CACHE_PATH$hash2$outFinal2 >/dev/null 2>&1 ";  
  $cmd = "{ " . $cmd1 . " ; " . $cmd2 . " ;} >/dev/null 2>&1 & " ;
 
  $output = null;  $retval = null;   
  $res = exec ( $cmd, $output, $retval );    
 // if ($VERBOSE) { $endTime = microtime (true); $duration = $endTime - $startTime; self::debugLog ("  completed manuBoth ($note1). DURATION: $duration RETURN: $retval \n"); }   

//  file_put_contents ( $CACHE_PATH . $hash. "$inFinal.mrk", ( $retval != 0 ? "ERROR" : "" ) ); // write ERROR into .mrk file if there is an error to know about this later when we only access file system // TODO !!!!!!!!!!!!!!!!!!!!
  if (! file_exists ( "$CACHE_PATH$hash2$inFinal2.pdf") )  {
    throw new Exception ("Tex2Pdf: Did not generate $CACHE_PATH$hash2$inFinal2.pdf for this content. Probably TeX error or transient problem while editing.");} 
//  return ($retval == 0 ? 0 : $output);  
}



/** GENERATE SVG from PDF via mutool. Transforms $hash.pdf into $hash$final.png
 */
private static function Pdf2SvgMT ($hash, $dpi, $final="_mt", $inFinal="_pdflatex") {
  $VERBOSE = true;
  $CACHE_PATH = CACHE_PATH;
  $cmd = MUTOOL. " convert -O resolution=$dpi -o $CACHE_PATH$hash$final.svg $CACHE_PATH$hash$inFinal.pdf  1-1";     // 1-1 is the page range    
  if ($VERBOSE) {$startTime = microtime(true);  self::debugLog ("Pdf2SvgMT started for $hash, command is: " . $cmd . "\n");}
  // $output = null;  $retVal = null;  //  exec ( $cmd, $output, $retVal );  
  exec ( $cmd );  
  if ($VERBOSE) {$endTime = microtime (true); $duration = $endTime - $startTime; self::debugLog ("  completed Pdf2SvgMT. DURATION: " . $duration . "\n"); }  
  // if ($VERBOSE) {self::debugLog ("  return value $retVal  output ". print_r ($output, true));} 
}



/** GENERATE PNG from PDF via GHOSTSCRIPT
 *   devices:   pngmono   pngray  png256    png16
 */
 private static function Pdf2PngGS ($hash, $dpi, $final="_gs", $device="pngmono", $inFinal="_pdflatex") {
   $VERBOSE = true;
   $CACHE_PATH = CACHE_PATH;
   $cmd = GHOSTSCRIPT . "  -dSAFER -dBATCH -dNOPAUSE -sDEVICE=$device -r$dpi -sPageList=1 -o $CACHE_PATH$hash$final.png $CACHE_PATH$hash$inFinal.pdf  ";    
   if ($VERBOSE) {$startTime = microtime(true);  self::debugLog ("Pdf2PngGS started for $hash, command is: " . $cmd . "\n");}
   // $output = null;  $retVal = null;  // do not need error messaging here
   exec ( $cmd );  
   if ($VERBOSE) {$endTime = microtime (true); $duration = $endTime - $startTime; self::debugLog ("  completed Pdf2PngGS. DURATION: " . $duration . "\n"); }   
 }




/** Generate dvi from tex using latex processor (works for ANY tex file, independently of precompilation settings) */

private static function Tex2DviLatex ($hash, $inFinal="") {
  $VERBOSE = true;
  $CACHE_PATH = CACHE_PATH;
  ASSERT_FILE ("$CACHE_PATH$hash$inFinal.tex");
  $cmd = LATEX_COMMAND . " -output-dir=$CACHE_PATH $CACHE_PATH$hash$inFinal.tex";              // MUST do a cd, since latex may generate some files in the local directory // TODO????????????????????????????? use build directoiry ???????
  if ($VERBOSE) {$startTime = microtime(true); self::debugLog ("Tex2DviLatex ($inFinal) for $hash$inFinal.tex, command is: $cmd \n");} 
  $output = null;  $retVal = null;
  exec ( $cmd, $output, $retVal ); 
  if ($VERBOSE) {$endTime = microtime (true); $duration = $endTime - $startTime; self::debugLog ("  completed Tex2DviLatex $inFinal for $hash$inFinal.tex. DURATION: $duration \n"); }

  // we could have partially usable LaTeX output and still have errors. This can be seen in the exit code and is signaled in the .mrk file across different endpoint invocations
  file_put_contents ( $CACHE_PATH . $hash. "$inFinal.mrk", ( $retVal != 0 ? "ERROR" : "" ) );
  if (! file_exists ( "$CACHE_PATH$hash$inFinal.dvi") )  {throw new Exception ("Tex2DviLatex: $inFinal Could not generate ANY DVI for this content. Probably TeX error or transient problem while editing.");} 
}

// CAVE: BUGS
/** Generate dvi from tex using pdflatex processor (works for ANY tex file, independently of precompilation settings) */
/** CAVE: It does not work to use pdflatex in dvi mode with a format which has been precompiled for pdf mode */
private static function Tex2DviPdflatex ($hash, $inFinal="") {
  $VERBOSE = true;
  $CACHE_PATH = CACHE_PATH;
  $cmd = PDFLATEX_COMMAND . " -output-format=dvi -output-dir=$CACHE_PATH $CACHE_PATH$hash$inFinal.tex";              // MUST do a cd, since latex may generate some files in the local directory // TODO????????????????????????????? use build directoiry ???????
  if ($VERBOSE) {$startTime = microtime(true); self::debugLog ("Tex2DviPdflatex command is: $cmd \n");} 
  $output = null;  $retVal = null;
  exec ( $cmd, $output, $retVal ); 
  if ($VERBOSE) {$endTime = microtime (true); $duration = $endTime - $startTime; self::debugLog ("  completed Tex2DviPdflatex for $hash$inFinal.tex. DURATION: $duration \n"); }

  // we could have partially usable LaTeX output and still have errors. This can be seen in the exit code and is signaled in the .mrk file across different endpoint invocations
  file_put_contents ( $CACHE_PATH . $hash. "$inFinal.mrk", ( $retVal != 0 ? "ERROR" : "" ) );
  if (! file_exists ( "$CACHE_PATH$hash$inFinal.dvi") )  {throw new Exception ("Tex2DviPdflatex: Did not generate $CACHE_PATH$hash$inFinal.dvi for this content. Probably TeX error or transient problem while editing.");} 
}







// TODO: dedpulicate this code with the same code in DanteBackup

// $cmd:       command to be executed
// $output:    captures stdout
// $error:     captures stderr
// $duration:  captures execution time in microseconds
// $verbose:   if true, write an invocation and completion log to debug
// $duration:  optional variable which will be set to the duration of the call, if provided by the caller
// return:     return value of the command
// CAVE 1: We MUST store the value of proc_open somewhere and we must release ressource using proc_close, otherwise things may go wrong
// CAVE 2: Similar with the pipes, which MUST be prepared, read and properly closed.

public static function executor ( string $cmd, &$output, &$error, $verbose=false, &$duration = null, $timeout = 0) {
  if ($verbose) {$cfn = debug_backtrace()[1]['function']; self::debugLog ( "$cfn calling shell executor\n"); }  // get name of the calling function

  $startTime  = microtime(true); 

  if ($timeout > 0) {$cmd = '/bin/bash -c "ulimit -t ' . $timeout . ';' . $cmd . '"';}  // add a timeout
  $proc       = proc_open($cmd,[ 1 => ['pipe','w'], 2 => ['pipe','w'],], $pipes);

  $output     = stream_get_contents($pipes[1]); fclose($pipes[1]);
  $error      = stream_get_contents($pipes[2]); fclose($pipes[2]);                  // MUST close pipes before doing a proc_close
  $closeParam = proc_close($proc);                                                  // wait for the process to finish and obtain exit value

  $duration   = microtime (true) - $startTime;
  if ($closeParam != 0) { self::debugLog ("Executor of $cmd closed with a non-zero return from proc_close: $closeParam\n" );}
  if ($verbose)         { self::debugLog ( "$cfn executor call completed.\n    Command: $cmd\n    DURATION: $duration\n    OUTPUT:--------\n$output\n--------\n    ERROR: $error\n" ); }
  return $closeParam;
}



// TODO migrate the debug comments in most functions which use the executor into the executor with a specifi variable requesting verbosity as parameter to the executor - as well as commentary on the caller 

/** Transform TeX into PDF
 *
 * $hash      hash value of the tex file, used to form output file name
 * $inFinal   additional identifier, used to form output file name
 * $note      debug identifier, printed into log if requested
 * $timeout   maximal runtime of the command
 *            if -1 : do not impose any runtime limit
 *
 *
 *  ERROR CONDITIONS:
 *    Hard error: Throw
 *    Soft error: Return non-zero (retval): Caller may show link to parsed latex error log
 *    No error: Return zero
 *   
 *    Returns:
 *      0 no error at all
 *      1 tex returned error, but we did not find an error message
 *    127 command not found
 *    string  error text as the error parser felt fit to parse it
 */
private static function Tex2Pdf ($hash, $inFinal, $note, $timeout=15) {
  $VERBOSE     = false;  
  $CACHE_PATH  = CACHE_PATH;
  $texFileName = "$CACHE_PATH$hash$inFinal.tex";
  $mrkFileName = "$CACHE_PATH$hash$inFinal.mrk";

  ASSERT_FILE ($texFileName); 

  $cmd = "pdflatex  --shell-escape --interaction=nonstopmode  -file-line-error-style -output-directory=$CACHE_PATH $texFileName"; 

  if ($timeout > 0) {$cmd = '/bin/bash -c "ulimit -t 2;' . $cmd . '"';}  // add a timeout

  if ($VERBOSE) { self::debugLog ("Tex2Pdf started ($note) for $hash$inFinal, command is: $cmd \n");}   
  $retval = TeXProcessor::executor ( $cmd, $output, $error, false );
  //  127   a fundamental error such as command not found
  //   1   a small tex error, but might be worth mentioning
  //   0   no error at all
  
  if ($retval == 0)        { $ret="";}
  else if ($retval == 127) { $ret="System error. Code 127. Check logs or inform manufacturer.";}
  else if ($retval == 1)   {
    $logfileContents = file_get_contents ( $CACHE_PATH . $hash . "_pc_pdflatex.log");
    if ($logfileContents === false) { $ret= "Tex signalled an error but we could not find an error file. Hash is ".$hash; }
    else {
      $index=strpos ($logfileContents, $texFileName.":");  // search for position of the tex file name where it is followed by a :
      $substring = substr($logfileContents, $index);       // go to this position
      $index=strpos ($substring,":");                      // advance to the position of the :
      $substring = substr ($substring, $index+1);          // jump over the :  this brings us to the line number
      $newlinePos = strpos($substring, "\n");              // search position of next newline
      if ($newlinePos === false) {$ret = $substring;}      // If there is no newline, return the entire string
      else {
        $secondNewlinePos = strpos($substring, "\n", $newlinePos + 1);     // Find the position of the second newline, starting the search just after the first newline
        if ($secondNewlinePos === false) { $ret = $substring; }   // If there is no second newline, return the substring from the start to the end of the string
        else {  
          $ret = substr($substring, 0, $secondNewlinePos);     // Return the substring from the start to the second newline
        }
      }   
    } 
  }
  else                     { $ret=" Unknown errorcode received from Tex driver. Value is ".$retval; }

  file_put_contents ( $mrkFileName, $ret );       // write ERROR into .mrk file if there is a soft error to know about this later when we only access file system
  
  if ($VERBOSE) {self::debugLog ("Tex2Pdf: Error: $ret \n");}

  return $ret;
}


static function fwrite_stream($fp, $string) {
    for ($written = 0; $written < strlen($string); $written += $fwrite) {
        $fwrite = fwrite($fp, substr($string, $written));
        if ($fwrite === false) {
            return $written;
        }
    }
    return $written;
}


///// TODO: it currently looks like in the piped mode we spawn pdftex processes but never properly close or release them
// checked using top in container.

private static function Tex2PdfPiped ($tex, $hash, $inFinal) {
  $VERBOSE = false;  
  $CACHE_PATH = CACHE_PATH;
  $texFileName = "$CACHE_PATH$hash$inFinal.tex";
//   -interaction=nonstopmode
  $cmd = "pdflatex  --shell-escape --interaction=batchmode  -file-line-error-style  -fmt=$CACHE_PATH/../extensions/Parsifal/formats_pdflatex/amsmath   -output-directory=$CACHE_PATH -jobname $hash$inFinal  > $CACHE_PATH/LOGG_PIPED"; 

  self::debugLog ("######## Tex2PdfPiped received: $tex \n");

//  $tex=str_replace ("\n","\n ", $tex);  // seems to be necessary for proper piping 
  //$tex="\\begin{document}hi\\n\\end{document}";

  $tex = "\\documentclass{standalone}\\begin{document}\\begin{minipage}[]{15cm}\\relax \\typeout{START-MARKER-TYPED-OUT-FOR-ERROR-PARSER}Abqi\\r\\njd\\typeout{END-MARKER-TYPED-OUT-FOR-ERROR-PARSER}\\end{minipage}\\end{document}";

  $proc = proc_open( $cmd,  array(0 => array('pipe','r'), 1 => array('pipe','w'), 2 => array('pipe', 'w')), $pipes, NULL );
  if (is_resource($proc)) {

   self::fwrite_stream ($pipes[0], $tex);
//   $written= fwrite($pipes[0], $tex, 10000);    // TODO: commata correction missing

  $output = stream_get_contents($pipes[1]);
  $errorOutput = stream_get_contents($pipes[2]);
  fclose($pipes[1]);
  fclose($pipes[2]);
  fclose($pipes[0]);

  $closed= proc_close($proc);

  }


  file_put_contents ( $CACHE_PATH . $hash. "$inFinal.mrk", 0 );    // TODO    is not the real error code !

  return "";  // TODO: should be some error code

}







/** GENERATE HTML from PDF with annotations using NODE-tool
 *  assume the existence of file   $hash.pdf    TODO: or different ??????
 *  return html text of annotation layer
 *  if $saveFile = true also save it as file $hash.html
 *
 */
static function generateHTML ($hash, $htmlScale =1, $horizontalDelta=0, $verticalDelta=0, $saveFile=true ) {
  $VERBOSE = false;  $CACHE_PATH = CACHE_PATH;  $PDF2HTML = PDF2HTML;
  $cmd = "$PDF2HTML $CACHE_PATH$hash_pdflatex.pdf  $CACHE_PATH$hash.html  $htmlScale  $horizontalDelta  $verticalDelta  >$CACHE_PATH$nameBase._html.stdout 2>$CACHE_PATH$nameBase._html.stderr";  
  if ($VERBOSE) {$startTime = microtime(true); self::debugLog ("generateHTML started for $hash, command is: $cmd \n");}     
  $output = null;  $retVal = null;
  exec ( $cmd, $output, $retVal ); 
  if (VERBOSE) {$endTime = microtime (true); $duration = $endTime - $startTime; self::debugLog ("  completed generateHTML. DURATION: $duration \n"); }
  if ($saveFile) {file_put_contents ( "$CACHE_PATH$hash.html", $output );}
  return $output;
}


// TODO: why do we still have this function - AND the function Tex2Pdf as well (which we use in lazyRenderer ???)
// TODO: deprecate this one here ??  
/** GENERATE PDF via PDFLATEX  from  $hash.tex => $hash.pdf
 *  Assume there is a $has.tex file, generate a $hash_pdflatex.pdf file using pdflatex
 *  Used jobname is $hash_pdflatex in order to have all intermediary files (such as .aux, .log) seperate from other tex engines which might be in use as well
 *  returns exit value
 */
static function generatePDF ($hash) {
  $VERBOSE = true;
  $CACHE_PATH = CACHE_PATH;
  ASSERT_FILE ("$CACHE_PATH$hash.tex");
  $cmd = PDFLATEX_COMMAND ." -output-directory=$CACHE_PATH $CACHE_PATH$hash.tex ";  
  if ($VERBOSE) { $startTime = microtime(true); self::debugLog ("generatePDF started for $hash, command is: $cmd \n");}   
  $output = null;  $retVal = null;   
  $res = exec ( $cmd, $output, $retval );    
  if ($VERBOSE) {  $endTime = microtime (true); $duration = $endTime - $startTime; self::debugLog ("  completed generatePDF. DURATION: $duration \n"); }         
  return $retVal;
}

/** generate bounding box information from pdfFileName
 *  construct the filename from the $hash and an additional qualifier for the pdf as in  $hash$inFinal.pdf
 *    $hashFinal is $hash, possibly with a precompilation tag, uniquely defining the $hashFinal.pdf file we need here
 */
static function generatePdfBboxGS ($hashFinal) {
  $VERBOSE = true;
  $GHOSTSCRIPT = GHOSTSCRIPT;
  $CACHE_PATH = CACHE_PATH;
  ASSERT_FILE ("$CACHE_PATH$hashFinal.pdf");
  $cmd = "$GHOSTSCRIPT -dBATCH -dNOPAUSE -dQUIET -sPageList=1 $CACHE_PATH$hashFinal.pdf 2>&1";  // need the stderr to stdout redirect since ghostscript outputs this on stderr
  if ($VERBOSE) {$startTime = microtime(true); self::debugLog ("generatePdfBboxGS started for $hashFinal, command is: $cmd \n");}  
  $output = null; $retVal = null;
  $res = exec ($cmd, $output, $retval);  // output should now be an array, one item per line and we should get two elements, the normal and the hi res bounding box  
                                         // if we get more there is either an error or we have a multi-page pdf  the first is the normal bounding box and it has string format as in:  %%BoundingBox: 0 0 426 823
  if ($VERBOSE) { self::debugLog ("  output obtained from ghostscript is:" . print_r($output, true) . "\n");}
  $txtArray = explode (" ",$output[0]);
  
  if ( !str_starts_with ($txtArray[0], "%%BoundingBox") ) { throw new ErrorException ("generatePdfBboxGS: could not find ghostscript bounding box answer for $hashFinal. Output obtained was ".print_r($output, true)); }
  
  if ($VERBOSE) {$endTime = microtime (true); $duration = $endTime - $startTime; $outputTxt =  "1=".$txtArray[1]." 2=".$txtArray[2]."".$txtArray[3]."".$txtArray[4]; self::debugLog ("  completed generatePdfBboxGS for $hashFinal. DURATION: $duration  returned $retval RESULT: $outputTxt \n");}  
  $result = array ( "left" => $txtArray[1], "top" => $txtArray[2], "left" => $txtArray[3], "left" => $txtArray[4]);
  return $result; 
}


/** get a width, height array for the Png file generated from the latex-dvi-dvipng path 
 *  assuming that the $hash.tex, $hash.dvi and $hash.png already exist or get 0 if file does not exist
 */
/* TODO DEPRECATE
static function getSizeFromDviPng ($hash) {
  $CACHE_PATH = CACHE_PATH;
  $path = $CACHE_PATH.$hash.".png";  
  if (file_exists ($path))  {$ims = getimagesize ( $path ); $ims["width"] = $ims[0]; $ims["height"] = $ims[1];} else { $ims = 0;}  
  return 0;
}
*/

/** assume the existence of $hash_pdflatex.pdf, produce a png, use it for cropping and produce an adjusted html
*/
static function generateNodePngHtml ($hash, $scale=2.54) {
  $VERBOSE = true;
  $CACHE_PATH = CACHE_PATH;
  $htmlScale = $pngScale = $scale;
  $cmd = NODE_BINARY . " " . NODE_SCRIPT. " " .  $CACHE_PATH . $hash. "_pdflatex.pdf " . $CACHE_PATH . $hash. "_node.png " . $CACHE_PATH . $hash. ".html " .  $pngScale . " " . $htmlScale  ." >". $CACHE_PATH . $hash."_node.stdout 2>" . $CACHE_PATH . $hash."_node.stderr";  
  if ($VERBOSE) {$startTime = microtime(true); self::debugLog ("generateNodePngHtml started for $hash, command is: $cmd \n");}  
  $output = null;  $retVal = null;  
  $res = exec ( $cmd, $output, $retval );
  if ($VERBOSE) {$endTime = microtime (true); $duration = $endTime - $startTime;  self::debugLog ("  completed generateNodePngHtml - DURATION: " . $duration . "  retval=".$retval."  res=".$res." \n"); }    
}



/////// TODO: there still is a permission problem with this thing here 

// TODO: in the background we want to do additional resolutions and themes 
/*
static function completeInBackground ($hash) {
  $CACHE_PATH = CACHE_PATH;
  //////// TODO: ADJUST and improve these settings !
  $pngScale = "2.54";    // if   "0"  we produce no png
  $htmlScale = "2.54";   // if  "0"  produce no html
  
  // NOTE: The reason that we do this so complicated is that we need a particular sequence of jobs to run in the background of php. 
  // We never got that running in *all* details, especially setting particular paths and shell variables for pdflatex and getting proper redirects and debug info

  $sheBang = "#!/bin/sh";
  $cd = "cd ".CACHE_PATH;
  $cmdPdflatex =  PDFLATEX_COMMAND . " -jobname " . $hash . "_pdflatex " .  $CACHE_PATH . $hash . ".tex " . ">".$CACHE_PATH.$hash."_pout  2>".$CACHE_PATH. $hash. "_perr";
  $cmdNode = NODE_BINARY . " " . NODE_SCRIPT. " " .  $CACHE_PATH . $hash . "_pdflatex.pdf " . $CACHE_PATH . $hash . "_node.png " . $CACHE_PATH . $hash . ".html " .  $pngScale . " " . $htmlScale  ." >". $CACHE_PATH . $hash."_nout 2>" . $CACHE_PATH . $hash."_nerr";  

  $shellFileName = $CACHE_PATH . $hash . ".sh";
  touch ($shellFileName);
  chmod ( $shellFileName, 0774);  
  if($shellFile = fopen( $shellFileName, 'w')) {
    fwrite($shellFile, $cd."\n".$sheBang."\n".$cmdPdflatex."\n".$cmdNode);  
    fclose($shellFile);
  } 
  exec ($shellFileName . " >/dev/null 2>/dev/null & ");    // kickoff and run in background.  ///// TODO: we do not want to keep output and stderr here ????
                                                           // MUST have a redirect of the stdout and stderr or we will block/https://stackoverflow.com/questions/14555971/php-exec-background-process-issues
}
*/


} // END of class


?>
