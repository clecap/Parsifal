<?php

require_once (__DIR__."/../config/config.php");
require_once ("polyfill.php");                       // include some PHP polyfill stuff
require_once ("Decorator.php");
require_once ("TeXGenerator.php");
require_once ("TeXCache.php");                       // APCu cache for the HTML produced by lazyRender

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
  $VERBOSE = true;
  $LATEX_FORMAT_PATH = LATEX_FORMAT_PATH; 
  $PDFLATEX_FORMAT_PATH = PDFLATEX_FORMAT_PATH;
  
  self::ensureEnvironment();
  if ($VERBOSE) {self::debugLog ("TeXProcessor::precompile: Tex2Pdf sees the following environment via getenv(): \n".print_r (getenv(), true) );}
  if ($VERBOSE) {
    self::debugLog ("\n TeXProcessor::precompile shel exec dumping environment sees: \n"); 
    exec ( "env >> ".LOG_PATH );
    self::debugLog ("\n --- DONE --- \n");  
  }

  self::cleanUpAll();  // clean up ALL existing /tmp files since all pages have to be re-done
  
  // clear the format files to ensure that we do not continue to use an old format when a compilation fails and produces no new format file
  foreach ([LATEX_FORMAT_PATH, PDFLATEX_FORMAT_PATH] as $dir) {
    foreach (["fmt", "fls", "log"] as $ext) {
      $file = "$dir$name.$ext";                                                                                                    // format paths already end with a slash
      if (!@unlink ($file) && file_exists ($file)) { throw new Exception ("precompile: could not remove old format file $file"); }   // a missing file is fine; a file that is still there is an error
    }
  }
  
  $cmd1 = LATEX_COMMAND . " -ini -recorder -output-directory=$LATEX_FORMAT_PATH \"&latex $LATEX_FORMAT_PATH/$name.tex\dump\" "; 
  if ($VERBOSE) { self::debugLog("TeXProcessor::precompile: will now execute the following latex precompile command: \n  ".$cmd1."\n"); }  
  $output1 = null; $retVal1 = null;  
  $retVal1 = DanteUtil::executor ($cmd1, $output1, $error1, null /* [TeXProcessor::class, "debugLog"]*/ , $duration1);
  if ($VERBOSE) { self::debugLog("TeXProcessor::precompile: latex command returned: $retVal1 and output: " . print_r ($output1, true)); }
  
  $cmd2 = PDFLATEX_COMMAND . " -ini -recorder -output-directory=$PDFLATEX_FORMAT_PATH \"&pdflatex $PDFLATEX_FORMAT_PATH/$name.tex\dump\" ";    
  if ($VERBOSE) { self::debugLog("TeXProcessor::precompile: will now execute pdflatex precompile command ".$cmd2."\n"); }
  $output2 = null; $retVal2 = null; 
  $retVal2 = DanteUtil::executor ($cmd2, $output2, $error2, null /* [TeXProcessor::class, "debugLog"]*/, $duration2);
  if ($VERBOSE) {self::debugLog("TeXProcessor::precompile: pdflatex command returned: $retVal2 and output: " . print_r ($output2, true)); } 
  if ($retVal1 != 0 || $retVal2 != 0) {
    return "\nCommand1 was: $cmd1\nRetVal1 was $retVal1\nOutput1 was " . print_r($output1, true) . "\n" . print_r ($error1, true) . "\n\n". 
           "\nCommand2 was: $cmd2\nRetVal2 was $retVal2\nOutput2 was " . print_r($output2, true) . "\n" . print_r ($error2, true);
  }
  else {return false;}
}







private static function getRemainingContent( $parsedData, $amsContent ) {
    // Simple logic to find content after the AMS tag
    $pos = strpos( $parsedData, $amsContent );
    if ( $pos !== false ) {
      return substr( $parsedData, $pos + strlen( $amsContent ) );
    }
    return '';
  }



const PREAMBLE_KEY = "parsifal-preamble";   // key of the <preamble> contents in the extension data of the ParserOutput

/**
 * Tag hook for <preamble>: remembers the tag content for all following Parsifal snippets of the same page; renders nothing.
 *
 * The content is kept in the ParserOutput (not on the Parser object), since MediaWiki creates a fresh ParserOutput for every parse,
 * whereas the Parser object is reused across parses and would leak the preamble of one page into the next.
 * A later <preamble> tag on the same page replaces an earlier one.
 *
 * @param string|null $in     content between <preamble> and </preamble>, null for a self-closing tag
 * @param array       $ar     attributes of the tag (unused)
 * @param Parser      $parser the MediaWiki parser
 * @param PPFrame     $frame  the preprocessor frame (unused)
 * @return string             empty string, the tag produces no output
 */
public static function renderPreamble ($in, $ar, $parser, $frame) {
  $parser->getOutput()->setExtensionData (self::PREAMBLE_KEY, (string) $in);
  return "";
}




public static function lazyRender ($in, $ar, $tag, $parser, $frame) {
  global $wgServer, $wgScriptPath, $wgAllowVerbose;

  $VERBOSE                 = true && $wgAllowVerbose;   // $VERBOSE = false && $wgAllowVerbose;

  $errorInformation = "";       // string which will be displayed as collected error information for the user in the UI

  $startTime               = microtime(true);

  $parserPreamble          = (string) $parser->getOutput()->getExtensionData (self::PREAMBLE_KEY);   // pick up contents of a preceding <preamble> tag on this page, see renderPreamble


  // TODO: chance for optimization: if file generated by generateTex already exists we might reuse it - curently we do not !
 
  // NOW we determine the mode:
  if ($tag == "tex")           { $mode = TeXCompilationMode::RAW;}
  else if ($tag == "amsmath")  { $mode = TeXCompilationMode::PC_PDFLATEX;}
  $modeString = $mode->value;      // mode as string is needed for file name construction

  $hash        = TeXGenerator::generateTex ($in, $tag, $mode, $ar, false, $parserPreamble);      // generate file $hash_pc_pdflatex.tex and return a hash of raw LaTeX source located in Mediawiki



  // the result of lazyRender might be cached, the key is the $hash of the TeX source
  $cRet = TeXCache::fetch ($hash, $cFlag, $startTime);
  if ($cFlag) { return $cRet; }



  // set some paths
  $annotationPath = constant("CACHE_PATH").$hash."_{$modeString}_final_3.html";                         // the local php file path under which we should find the annotations in form of a (partial) html file  // TODO: hardcoded resolution is bad
  $finalImgPath   = constant("CACHE_PATH").$hash."_{$modeString}_final_3.png";   // TODO: cave hardcoded resolution is bad
  $errorPath      = "$wgScriptPath/extensions/Parsifal/html/texLog.html?"."$wgServer$wgScriptPath".CACHE_URL.$hash."_{$modeString}";
  $mrkFileName    = constant("CACHE_PATH") . $hash. "_$modeString.mrk";
  $lockFileName   = "/var/lock/parsifal/$hash";                                    // lock the hash, since multiple invocations may induce race conditions (we had that case) 
  // CAVE: must lock in /var/lock, since this is not on the mounted volume (where locks do not work) but natively in the container (where locks work)

  $lockStream = fopen ($lockFileName, 'c' );   // create the file
  if ( !$lockStream ) { throw new Exception ("Could not open lock file $lockFileName. This should not happen. ");}

  $ret = "<!-- DEFAULT VALUE just for declaration inside of php, should be overwritten -->";

  if (flock ($lockStream, LOCK_EX ) ) {  // self::debugLog ("lazyRender obtained a lock on $lockFileName at " .microtime(true). "\n");
    try {  // BEGIN EXCEPTION PROTECTED AREA

      $property = $parser->getOutput()->getPageProperty ("Parsifals");        // page should get a property indicating the number of parsifal controlled areas
      $parser->getOutput()->setNumericPageProperty ("Parsifals", ( is_numeric ($property) ? $property+1: 1 ));  // numeric variant, since setPageProperty with non-string value is deprecated since MW 1.42

      $timestamp = date_format( new DateTime(), 'd-m-Y H:i:s');               // want a timestamp in the img tag on when the page was translated for debugging purposes
      self::ensureEnvironment ();

      // throw new Exception ("THIS is a test for exception handling");  // just for testing exception handling

      /**** LATEX to PDF production step **/
      $timePDF = microtime (true);                                                  // true: float seconds; without it microtime returns a string "msec sec" which cannot be subtracted

      // determine necessary number of runs
      $numRuns = 1;

      $softError = "";
      if ($VERBOSE) {self::debugLog ("lazyRender LATEX2PDF phase for $hash... ") ;}
      if ( !file_exists (constant("CACHE_PATH") . $hash . "_{$modeString}.pdf" ) ) {   //  *** CASE 1: PDF file does not exist: make PDF and pick up error status from function
        if ($VERBOSE) {self::debugLog ( "lazyRender: CASE 1: did not find file " . constant("CACHE_PATH") . $hash . "_{$modeString}.pdf, starting TeX2PDF processing for hash= " . $hash. "\n");}

        $softError =  self::Tex2Pdf ($hash, "_{$modeString}", "lazyrender", 15, $numRuns) ;

        if ($VERBOSE) {self::debugLog ( "TeX2PDF processing for hash=$hash returned error status: ($softError) \n" );}
        if ( !file_exists (constant("CACHE_PATH") . $hash . "_{$modeString}.pdf" ) ) {
          if ($VERBOSE) {self::debugLog ( "After TeX2PDF processing for hash=$hash but cannot find a PDF file\n" );}
          if ( strlen ($softError) == 0) { $softError = "Transient Latex error - could not produce PDF file\n";} // if condition is required to not overwrite existing latex error info with this
        }
        else { if ($VERBOSE) {self::debugLog ( "After TeX2PDF processing for hash=$hash I found the PDF file\n" );} }
      }
      else {                                                                                           //  *** CASE 2: PDF file exists already: pick up error status from error marker file
        if ($VERBOSE) {self::debugLog ( "lazyRender: CASE 2: found PDF file for $hash on disc, picking up old error status from marker file \n" ); }
        // still need to pick up error information from the last run, since the error might not have been fixed by the user, so we still must display it
        if ( ( $softError = @file_get_contents ( $mrkFileName ) ) === false) { throw new ErrorException ("lazyRender: Could not find error marker file " . constant("CACHE_PATH") . $hash. "_{$modeString}.mrk"); }
      }
      $timePDF = microtime (true) - $timePDF;
      // self::debugLog ("lazyRender: LATEX2PDF phase took $timePDF [sec] \n");



    $errorInformation .= $softError;


      /**** PDF to PNG and HTML production step  **/
      // We need a width and height in the img tag to assist the browser to a more smooth and flicker-less reflow.
      // The width MUST be equal to the width of the image (or else the browser must rescale the image, which BLURS the image and takes TIME)
      
      $imgExists  = file_exists ($finalImgPath);  
      $annoExists = file_exists ($annotationPath);
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

        $inFinal =  "_{$modeString}";  // "_pc_pdflatex";
        $outFinal = "_{$modeString}_final_3";     // "_pc_pdflatex_final_3";


        $retVal2=self::Pdf2PngHtmlMT ($hash, $pdfscale, $inFinal, $outFinal, $width, $height, $duration );  

        $errorInformation .= $retVal2;

        if ($VERBOSE) {self::debugLog ("Pdf2PngHtmlMT for $hash completed in $duration with return value $retVal2 \n"); }                            // what about an error status here ???????? TODO: 
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
      $naming = ( array_key_exists ("n", $ar) ? "data-name='".htmlspecialchars ($ar["n"], ENT_QUOTES)."' " : "");             // prepare a data-name attribute for the image

      // image tag style
      $style = "style=\"";
      $markingClass = "";
      if ( array_key_exists ("number-of-instance", $ar) ) { $style .= "margin:20px;";   // TODO: usage !!
        $markingClass = "instance_".$ar["number-of-instance"];
      }

      Decorator::addStyle ( $ar, $style );   // add additional attributes to the style $style, depending on the array

      $style .= "width:100%; vertical-align: baseline; display:none;\"";  // vertical-align:baseline: the page around the image flickers a bit when parsifal runtime makes them visible with showImage - this prevents it

      $titleInfo = "";  // currently unused 
      $dataHash  = "data-hash=\"".$hash."\"";                   // attribute helpful for debugging and maybe more
      $onShow    = "onload=\"this.style.display='block';\"";    // function which turns off image and only turns on after completed load; protects user from seeing half-loaded images, which DOES happen for longer texts

      $srcImg    = 'src="'.$wgServer.$wgScriptPath.CACHE_URL.$hash."_{$modeString}_final_3.png".'"'; 

      // TODO: identical contents leads to identical hashes leads to two elements with the same id, which is made
      //       we are / should be migrating this to using $dataHash only !
      $imgTag      = "<img $naming id=\"$hash\" $dataHash  data-timestamp='$timestamp'  $style $onShow class='texImage' alt='Image is being processed, please wait, will fault it in automatically as soon as it is readyss' $srcImg ></img>";

      $annotations  = (file_exists ($annotationPath) ? file_get_contents ($annotationPath) :  null );   // get annotations, if no file present, use null

      /** ADD decorations */
      $core = new Decorator ( $imgTag, $width, $height, $markingClass);
      $core->wrap ( $annotations, $errorInformation, $errorPath, $titleInfo, $hash);      // wrap with annotations and error information   
      $core->collapsible ( $ar );                                                  // decorate with collapsibles

      $ret = $core->getHTML ();                                                        // generate HTML which includes the decorations

    } // try

    // in case of exception, build a suitable error element for return
    catch (\Exception $e) { $msg=$e->getMessage(); $stk=$e->getTraceAsString(); self::debugLog ("lazyRenderer: Exception: $e \n$msg\n$stk\n\n");  $ret = "<b>$msg</b><br>$stk<br>";} 
    catch (\Throwable $e) { $msg=$e->getMessage(); $stk=$e->getTraceAsString(); self::debugLog ("lazyRenderer: Thworable: $e \n$msg\n$stk\n\n");  $ret = "<b>$msg</b><br>$stk<br>";}
    finally               { fclose ($lockStream); }  // self::debugLog ("lazyrender returned a lock for $hash at ".microtime(true) . " \n");

    // cache the result we just generated so we need not regenerate it from the files
    TeXCache::store ($hash, $ret);
 
  } // end if (flock) 
  else { self::debugLog ("lazyRenderer: Could not obtain lock for $hash \n"); }  // NOTE: lock files are removed by regular crontab script

  if (true || $VERBOSE) {$endTime = microtime (true); $totalDuration = $endTime - $startTime;  self::debugLog ("lazyRender for $hash completed in $totalDuration [sec] \n\n");}

  return $ret;
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



// TODO: DO WE STILL NEED THIS?  MAYBE WE CAn DEPRECATE THIS ASPECT BY preparing the environment elsewhere and plugging it in into proc_open somehow?
/* ensures that PHP has the right concept of a processing environment, as required for TeX  */
private static function ensureEnvironment () {
  $VERBOSE  = true;
  $ALREADY  = "already";

  $start = hrtime (true);  // only for development - time this, results see below

  $parsifalenvironment = getenv('PARSIFALENVIRONMENT');
  if ( strcmp ($parsifalenvironment, $ALREADY) == 0 ) {
    if ($VERBOSE) {self::debugLog ("TeXProcessor::ensureEnvironment: environment has already been setup - exiting \n");}
    return;
  }
  else {if ($VERBOSE) {self::debugLog ("TeXProcessor::ensureEnvironment: environment MUST still be setup \n");}}

  // if ($VERBOSE) {self::debugLog ("TeXProcessor::ensureEnvironment is before the environment patching and sees the following environment: \n".print_r (getenv(), true) );}  
  // if ($VERBOSE) {self::debugLog ("TeXProcessor::ensureEnvironment patches PATH=".PATH."\n" );}  

  // putenv disabled via disable_functions would raise an Error on the first call, so check for it explicitly
  if (!function_exists ("putenv")) {throw new Exception ("Parsifal was unable to putenv environment. Must check if putenv is enabled in php.ini or similar!");}

  $flag = true;      // set the environment variables required by TeX; && combines every putenv result into $flag
  // NOTE: max_print_line, error_line, half_error_line are no longer set here but passed per command via TEX_LOG_OPTIONS (config.php)

  $flag = ($flag && putenv ("PATH=".PATH));      // need PATH to tex binaries and to standard OS binaries such as sed and others for font generation scripts
  $flag = ($flag && putenv ("HOME=".HOME));
 
  $flag = ($flag && putenv ("TEXINPUTS=".TEXINPUTS));   // want to access local style and packges files from a local directory

  if ($flag === false) {throw new Exception ("Parsifal was unable to putenv environment. Must check if putenv is enabled in php.ini or similar!");}

 
  putenv ("PARSIFALENVIRONMENT=already"); // mark the environment as set up only after all variables were set successfully, so a failed setup is retried on the next call

  //if ($VERBOSE) {self::debugLog ("TeXProcessor::ensureEnvironment: putting PATH to env returned: ".print_r ( putenv("PATH=".PATH) . "\n", true) );}  
  //if ($VERBOSE) {self::debugLog ("TeXProcessor::ensureEnvironment: getting PATH from env returned: ".print_r ( getenv("PATH") . "\n", true) );}  
  //if ($VERBOSE) {self::debugLog ("TeXProcessor::ensureEnvironment: after environment patching sees the following environment: \n".print_r (getenv(), true) );}  

  $duration = hrtime(true)-$start; self::debugLog ("TeXProcessor::ensureEnvironment duration: $duration \n");  // negligent. some 20 micro seconds
}



/* ensure existence of a disc cache directory */
public static function ensureCacheDirectory () {
  if ( !file_exists ( constant ("CACHE_PATH") ) ) {
    self::debugLog( "TeXProcessor::ensureCacheDirectory: detected a missing cache directory; trying to construct: ". constant ("CACHE_PATH")."\n");     
    $retVal = mkdir ( constant ("CACHE_PATH"), 0755);  
    self::debugLog( "  mkdir returned $retVal \n");
  }
}


/* ensure existence of a lock directory */
public static function ensureLockDirectory () {
  if ( !file_exists ( constant ("LOCK_DIR_PATH") ) ) {
    self::debugLog( "TeXProcessor::ensureLockirectory: detected a missing lock directory; trying to construct: ". constant ("LOCK_DIR_PATH")."\n");     
    $retVal = mkdir ( constant ("LOCK_DIR_PATH"), 0777);  
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
// TODO: make this independen of the mode code pc_pdflatex, as there are different modes as well
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
// TODO: make this independen of the mode code pc_pdflatex, as there are different modes as well
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



// CURRENTLY USED 
/** GENERATE PNG from PDF. Transforms $hash$inFinal.pdf into $hash$inFinal.png */
/*
 *
 *  returns an empty string for ok or an error information string
 *
 */
private static function Pdf2PngHtmlMT ($hash, $scale, $inFinal, $outFinal, &$width, &$height, &$duration = null) {
  $VERBOSE = true;
  $CACHE_PATH = CACHE_PATH;  $PY_PATH = PY_PATH;

  $cmd = "$PY_PATH/make.py $scale $CACHE_PATH$hash$inFinal $CACHE_PATH$hash$outFinal ";
  if ($VERBOSE)  { self::debugLog ("\n TeXProcessor::Pdf2PngHtmlMT $hash starting: \n   command: $cmd"); }
  $retVal = DanteUtil::executor ($cmd, $output, $error, null /* [TeXProcessor::class, "debugLog"]*/, $duration);

  $values = explode(' ', $output);
  list($width, $height) = sscanf($output, "%f %f");

  if ($VERBOSE)  { 
    self::debugLog ("\n\nPdf2PngHtmlMT: pymupdf output            for $hash is:\n".$output. "\n"); 
    self::debugLog (    "Pdf2PngHtmlMT: pymupdf return/exit value for $hash is:\n".$retVal. "\n"); 
    self::debugLog (    "Pdf2PngHtmlMT: pymupdf error             for $hash is:\n".$error.  "\n\n");
  }


  if ($retVal == 0) { $errorString = "";}
  else {
    // make.py marks its own error reports with "make.py ERROR:"; prefer these over the full stderr, which also contains debug output
    $errLines    = preg_match_all ('/^make\.py ERROR: .*$/m', (string) $error, $matches) ? implode ("\n", $matches[0]) : $error;
    $errorString = "pdf postprocessing returned $retVal with $errLines ";
  }


  return $errorString;
}





// CURRENTLY NOT UISED
// this is the variant returning the html / svg / annotation code directly
private static function Pdf2PngHtmlMT_RET ($hash, $scale, $inFinal, $outFinal, &$duration = null) {
  $VERBOSE = true;
  $CACHE_PATH = CACHE_PATH;  $PY_PATH = PY_PATH;

  $cmd = "$PY_PATH/make2.py $scale $CACHE_PATH$hash$inFinal $CACHE_PATH$hash$outFinal "; 
 if ($VERBOSE)  { self::debugLog ("\n TeXProcessor:: executor $hash starting: \n"); }
  $retVal = DanteUtil::executor ($cmd, $output, $error, null /* [TeXProcessor::class, "debugLog"]*/, $duration);
 if ($VERBOSE)  { self::debugLog ("\n TeXProcessor:: executor $hash finished: \n"); }
  
  if ($VERBOSE)  { self::debugLog ("\n TeXProcessor:: mutool execution for scale=$scale hash=$hash had a duration of: ".$duration . "\n"); }
  if ($VERBOSE)  { self::debugLog ("\n TeXProcessor:: mutool run output $hash shellexecutor: \n".$output); }
  // TODO: better error handling


  return $output;
}






// TODO: dedpulicate this code with the same code in DanteBackup

// $cmd:       command to be executed
// $output:    variable which captures stdout
// $error:     variable which captures stderr
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
 * $numRuns   Number of processor runs requested, default 1 but could be more for minted, references, bibtex and similar stuff
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
private static function Tex2Pdf ($hash, $inFinal, $note, $timeout=15, int $numRuns=1) {
  $VERBOSE     = true;  
  $CACHE_PATH  = CACHE_PATH;
  $texFileName = "$CACHE_PATH$hash$inFinal.tex";
  $mrkFileName = "$CACHE_PATH$hash$inFinal.mrk";

  ASSERT_FILE ($texFileName);                                                               // ensures the TeX source file exists
  $cmd = PDFLATEX_COMMAND . " --shell-escape -output-directory=$CACHE_PATH $texFileName";          

  if ($VERBOSE) { self::debugLog ("Tex2Pdf started ($note) for $hash$inFinal, command is: $cmd \n");}   
  // $retval = DanteUtil::executor ( $cmd, $output, $error, [TeXProcessor::class, "debugLog"] );

  // run the TeX processor $numRuns times
  $numRuns = min (5, max (1, $numRuns));                                                    // clamp number of runs to 1 ... 5
  for ($run = 1; $run <= $numRuns; $run++) {
    $retval = DanteUtil::executor ( $cmd, $output, $error, null, $duration, $timeout );
    if ($VERBOSE) { self::debugLog ("Tex2Pdf run $run of $numRuns for $hash$inFinal returned $retval after $duration s\n"); }
    if ($retval != 0) { break; }
  }

  //  127   a fundamental error such as command not found
  //   1   a small tex error, but might be worth mentioning
  //   0   no error at all
  
  if ($retval == 0)        { $ret="";}
  else if ($retval == 127) { $ret="System error. Code 127. Check logs or inform manufacturer.";}
  else if ($retval == 1)   {
    self::debugLog ("Tex2Pdf: executor returned small error, retval=" .$retval."\n");
    $logfileContents = @file_get_contents ( $CACHE_PATH . $hash . "_pc_pdflatex.log");
    if ($logfileContents === false) {   // have no log file
      self::debugLog ("Tex2Pdf: we could not find an error file for hash ".$hash."\n");
      $ret= "Tex2Pdf: we could not find an error file. Hash is ".$hash; }
    else { // have a log file
      $index=strpos ($logfileContents, $texFileName.":");  // search for position of the tex file name where it is followed by a :
      if ($index === false) { // did not find needle in error parse attempt
        self::debugLog ("Tex2Pdf: error parser did not find needle $texFileName:\n");
        $ret="Unable to find specific error message in log file";
      }
      else { // found a needle in error parse attempt
        $substring = substr($logfileContents, $index);         // go to this position
        $index=strpos ($substring,":");                      // advance to the position of the :
        $substring = substr ($substring, $index+1);            // jump over the :  this brings us to the line number
        $newlinePos = strpos($substring, "\n");              // search position of next newline
        if ($newlinePos === false) {$ret = $substring;}                         // If there is no newline, return the entire string
        else {
          $secondNewlinePos = strpos($substring, "\n", $newlinePos + 1);     // Find the position of the second newline, starting the search just after the first newline
          if ($secondNewlinePos === false) { $ret = $substring; }   // If there is no second newline, return the substring from the start to the end of the string
          else {  
            $ret = substr($substring, 0, $secondNewlinePos);     // Return the substring from the start to the second newline
          }
        }
      }

    } // end of else: have a logfile
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



} // END of class


?>
