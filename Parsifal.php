<?php

/***
 * 
 * PHP calculates hash value and generates an img tag with a src containing the hash value (as postfix of preview.php) and a data-texsrc dataset
 * img attemtps loading via preview.php with hash value as query postfix; this may succeed and then is the normal display speef we currently have
 * if the img loading fails the onerror handler faults in the image, taking the latex source from the data-texsrc.
 * 
 */
  
          
use MediaWiki\MediaWikiServices;

require_once ("config/config.php");                // include path configuration
require_once ("php/TexProcessor.php");


class Parsifal {                                  // glue class of the extension

  public static function onParserFirstCallInit ( Parser $parser ) {  // Register parser callback hooks
    global $initialHashsUsed;  // TODO ????

    $VERBOSE = true;
    $title = $parser->getTitle();
    if ($VERBOSE) {TeXProcessor::debugLog( "-------- Parsifal::onParserFirstCallInit called for page of title: ".$title.  " \n");}
    
    ///// ???? not clear what this is ??????
    if (property_exists ($parser, "calledFromParsifalFullPage") && $parser->calledFromParsifalFullPage) {
        $parser->setHook ( 'block',            function ($in, $ar, $parser, $frame) { return "<div style='width:619px;color:red;'>---------------" . $in . "</div>";  }    );         // implement a <block> construct  // TODO: make width adjustable !!
        return;
    }


    foreach (TAGS as $key => $tag) { $parser->setHook ($tag, function  ($in, $ar, $parser, $frame) use ($tag) {
      if ( array_key_exists ("double", $ar) ) {
        $ar["number-of-instance"] = "1";
        //$res1 = TeXProcessor::lazyRender ($in, $ar, "amsmath", $parser, $frame);
        $ar["number-of-instance"] = "2";
        //$res2 = TeXProcessor::lazyRender ($in, $ar, "amsmath", $parser, $frame); 
        return $res1.$res2;
      }
      else { 
        //TeXProcessor::debugLog( "Parsifal calling lazyRender (1 of 1)\n");
        $res = TeXProcessor::lazyRender ($in, $ar, $tag, $parser, $frame);
        //TeXProcessor::debugLog( "Parsifal sees return of lazyRender (1 of 1)\n");
        return $res;
      }
      return;  } );
     } // for every tag in TAGS implement a Latex-like parser hook


    $parser->setHook ( 'preamble', [ "TeXProcessor",  'renderPreamble']  );   

    $parser->setHook ( 'block',            function ($in, $ar, $parser, $frame) { return  Parsifal::block ($in, $ar);}    );         // implement a <block> construct

  }




  public static function onParserBeforeInternalParse( Parser &$parser, &$text, &$strip_state ) {
    $VERBOSE = false;
    if ($VERBOSE) {TeXProcessor::debugLog( "-------- Parsifal::onParserBeforeInternalParse ".$text.  " \n");}

 //   $text = preg_replace('/<\/amsmath></', '</amsmath><!-- immediate --><', $text);
 //   $text = preg_replace('/<\/amsmath>[ ]*\n</', '</amsmath><!-- newline --><', $text);
 //   $text = preg_replace('/<\/amsmath>[ ]*\n[ ]*\n[ ]*</', '</amsmath><!-- empline --><', $text);

    $text = preg_replace('/<\/amsmath></',                           '</amsmath><div class="parsifal" data-strut="immediate"></div><', $text);
    $text = preg_replace('/<\/amsmath>[ ]*\n</',                     '</amsmath><div class="parsifal" data-strut="newline"></div><', $text);
    $text = preg_replace('/<\/amsmath>[ ]*\n[ ]*\n[ ]*</',           '</amsmath><div class="parsifal" data-strut="empline"></div><', $text);
    $text = preg_replace('/<\/amsmath>[ ]*\n[ ]*\n[ ]\n[ ]*</',      '</amsmath><div class="parsifal" data-strut="twoline"></div><', $text);

    // remove blanks and newlines after a preamble declaration, as mediawiki parser would generate spaces, <p> and <br> if we do not do this
    $text = preg_replace('/<\/preamble>\s+/',    '</preamble>', $text);

   if ($VERBOSE) {TeXProcessor::debugLog( "-------- Parsifal::onParserBeforeInternalParse AFTER REPLACE".$text.  " \n");}
    return true;
  }



  // THIS thing builds the buttons on the tab line of the vector template
  public static function onSkinTemplateNavigation( $skin, &$actions ) {
    global $wgTitle, $wgEmailPageGroup;
    if( is_object( $wgTitle ) ) {
      //  $actions['views']['email'] = array( 'text' => wfMessage( 'email' )->text(), 'class' => false, 'href' => $url );  // show on main line CHC
      //$actions['actions']['purge'] = array(  'class' => false, 'href' => "" );  // show under "more" button  CHC
     // $actions['actions']['rebuild']  = array(  'text'=> "Rebuild",   'class' => false, 'href' => "" );  // show under "more" button  CHC        // href can be a javascript: URL
      $actions['actions']['Fullpage'] = array(  'text' => "FullPage", 'title' => 'Download a Latex version', 'class' => false, 'href' => $wgTitle->getLocalURL ('action=fullpage') );  // show under "more" button  CHC        // href can be a javascript: URL      
    }
    return true;
  }



  public static function onPageSaveComplete( WikiPage $wikiPage, MediaWiki\User\UserIdentity $user, string $summary, int $flags, MediaWiki\Revision\RevisionRecord $revisionRecord, MediaWiki\Storage\EditResult $editResult ) {
    global $wgTopDante;
    TeXProcessor::debugLog( "\n\nParsifal::onPageSaveComplete entered for: title=". $wikiPage->getTitle()." namespace=" . $wikiPage->getTitle()->getNamespace() . " \n");
      
//    if ($wikiPage->getTitle()->getNamespace() == NS_MAIN) {} else { TeXProcessor::debugLog( "Parsifal: due to namespace not working\n" ); }


        return true;

   TeXProcessor::debugLog( "**************************** Parsifal::onPageSaveComplete will leave now \n");
  }  
  
  
  static public function block       ($in, $ar)   {return "<div class='parsifalBlock'>" . $in . "</div>";}


// this provides an early insert into the body to provent FOUC 
// NOTE: This is only called for normal pages and not for the edit page 
public static function onOutputPageBeforeHTML( OutputPage &$out, &$text ) {
  // must NOT have an entry in extension.json with any script file, otherwise this thing here does not work
  // must addModuleStyles separately in order to prevent FOUC 
  $out->addModuleStyles ( ["ext.Parsifal"] );                               

  $title = $out->getTitle();
  //   if ( !$title instanceof Title || !$title->exists() ) { return true; }  // Ensure the title object is valid
  $pageID       = $title->getArticleID();
  $services     = MediaWikiServices::getInstance();
  $pageProps    = $services->getPageProps();
  $properties   = $pageProps->getProperties( $title,  ['Parsifals', 'Todos']  );
  // at this place in the code it is not necessarily clear that the page already had gotten ANY property, so we must check!
  // if a property was set, it was set in TexProcessor::lazyRenderer

  if ( array_key_exists ($pageID, $properties) ) {
    $myProperties     = $properties [$pageID];
      if ( array_key_exists ('Parsifals', $myProperties) ) {
        $parsifalProperty = $myProperties['Parsifals'];
        $out->setIndicators ( ["Parsifals" => $parsifalProperty ]);}
  }
}


  // this provides a very early injection into the header 
  public static function onBeforePageDisplay ( OutputPage $out ) {
    global $wgExtensionAssetsPath;
    //$loader = "load.php?modules=startup|ext.Parsifal&only=scripts";                       // URL for loading cached, minified form of the file
    //$out->addHeadItem ("runtime.js", "<script src='$loader'></script>");         // NOTE: must be loaded synchronously since we need it while we are building up the page!
        $out->addHeadItem ("runtime.js", "<script src='$wgExtensionAssetsPath/Parsifal/js/runtime.js'></script>");  // during development: load raw version directly
  }


  // inject a section action for editing the section with code mirror
  public static function onSkinEditSectionLinks ( $skin, $title, $section, $tooltip, &$links, $lang ) {
    $links['editsection'] = [   // override existing editsection link
      'targetTitle' => $title,
      'text' => "edit",
      'attribs' => ["title" => "Edit with Code Mirror"],
      'query' => array( "action" => "edit", "section" => $section, "editormode" => "codemirror" ),
      'options' => array(),
    ];
  }


  public static function onRegistration () { 
    global $wgFileExtensions;
    TeXProcessor::ensureCacheDirectory ();  // required for proper execution

    // allow uploads of relevant file txpes
    if ( !in_array( 'tex', $wgFileExtensions ) ) {$wgFileExtensions[] = 'tex';}
    if ( !in_array( 'sty', $wgFileExtensions ) ) {$wgFileExtensions[] = 'sty';}
    if ( !in_array( 'cls', $wgFileExtensions ) ) {$wgFileExtensions[] = 'cls';}
    if ( !in_array( 'bib', $wgFileExtensions ) ) {$wgFileExtensions[] = 'bib';}
    if ( !in_array( 'bst', $wgFileExtensions ) ) {$wgFileExtensions[] = 'bst';}

 }





  // this hook is used to ensure that after editing a Parsifal template in the MediaWiki namespace the file is copied back to the file system
  // this is necessary since the templates must be picked up from the file system 
  public static function afterAttemptSave (EditPage $editPage, Status $status, $details) {
    $VERBOSE = false;
    $TEMPLATE_PATH = TEMPLATE_PATH; $LATEX_FORMAT_PATH = LATEX_FORMAT_PATH;   $PDFLATEX_FORMAT_PATH = PDFLATEX_FORMAT_PATH;
    if ($VERBOSE) {TeXProcessor::debugLog( "Parsifal::afterAttemptSave called, title is namespace is ".$editPage->getTitle()->getNamespace()." \n");}

    if ( $editPage->getTitle()->getNamespace() == NS_MEDIAWIKI) {            // only if the edit takes place in the MediaWiki namespace
      $titleText = $editPage->getTitle()->getText();
      if ( str_starts_with($titleText, "ParsifalTemplate/") ) {  Parsifal::reconstructFormat ( $titleText );}
    }  
  }  // end function afterAttemptSave



// reconstructs format files, call with   "ParsifalTemplate/amsmath"  or similar
  public static function reconstructFormat ( $titleText ) {
    $VERBOSE = false;
    $TEMPLATE_PATH = TEMPLATE_PATH; $LATEX_FORMAT_PATH = LATEX_FORMAT_PATH;   $PDFLATEX_FORMAT_PATH = PDFLATEX_FORMAT_PATH;
    if ($VERBOSE) {TeXProcessor::debugLog( "Parsifal::reconstructFormat called, titleText is ". $titleText . "\n");}

    if ( str_starts_with($titleText, "ParsifalTemplate/") ) {              // and if the page is a subpage of ParsifalTemplate/
      $shortName = str_replace ("ParsifalTemplate/", "", $titleText);
        
      if ($VERBOSE) {TeXProcessor::debugLog("Parsifal::reconstructFormat: copying MediaWiki:$titleText to $shortName.tex \n");}
         
        $myTitle   = Title::newFromText( $titleText, NS_MEDIAWIKI );
        $myArticle = new Article( $myTitle );
        $content = $myArticle->getPage()->getContent();
        if (!$content) {throw new Exception ("Could not get content for page $titleText, maybe the Template does not exist");}
        $template  = $content->getNativeData();  // the data in MediaWiki:ParsifalTemplate/$shortName

        $templateFileName = "$TEMPLATE_PATH$shortName.tex";
        if ($VERBOSE) {TeXProcessor::debugLog("Parsifal::reconstructFormat: copying MediaWiki:ParsifalTemplate/$titleText to $templateFileName \n");}          
        file_put_contents( $templateFileName, $template, LOCK_EX); 
          
        // remove everything beginning with \begin{document}...   and place the rest as source into the format directories
        $index = strpos ($template, "\begin{document}");
        $templatePrecompileSrc = substr ($template, 0, $index);
          
        $templatePrecompileSrc = preg_replace ( '/(^<pre>\s*$)|(<^\/pre>\s*$)/m', "", $templatePrecompileSrc );   // remove <pre> ... </pre> which improves display of page
        $templatePrecompileSrc = Parsifal::injectTemplate ($templatePrecompileSrc);
          
        file_put_contents( "$LATEX_FORMAT_PATH$shortName.tex",    $templatePrecompileSrc, LOCK_EX);    
        file_put_contents( "$PDFLATEX_FORMAT_PATH$shortName.tex", $templatePrecompileSrc, LOCK_EX);        
        
        TeXProcessor::cleanUpAll ();                                       // cleanup all files since a fresh template requires regeneration anyhow
        // TODO: A---------------------------- need to register dependencies on the templates 

        if ($VERBOSE) {TeXProcessor::debugLog("Parsifal::reconstructFormat: will now invoke precompiler on $shortName \n");}          
        $inError = TeXProcessor::precompile ($shortName);
        
        if ($inError == false) {  // no precompilation error
            if ($VERBOSE) {TeXProcessor::debugLog("Parsifal::reconstructFormat: completed precompilation successfully on $shortName \n");}             
          }
        else {
          throw new Exception ("Error when compiling the Template. Reverting the change. Full error is: \n" . $inError);
        }
        if ($VERBOSE) {TeXProcessor::debugLog("Parsifal::reconstructFormat completed on $titleText\n");}
    }
  }





  // take the string contents $text and replace all lines of the form Mediawiki:ParsifalInclude/<name> by the content of this file
  private static function injectTemplate ($text) {
    $regExp = "/^MediaWiki:ParsifalInclude\/[a-zA-Z0-9\-_\/]+/m";
    preg_match_all ($regExp, $text, $matches);
    $found = $matches[0];  // array of all matches found
    // die (print_r ( $found, true));
    foreach ($found as $titleText) {
      $myTitle   = Title::newFromText( $titleText, NS_MEDIAWIKI );
      $myArticle = new Article( $myTitle );
      if ($myArticle->getPage()->getContent() == null) {
        throw new Exception ("Could not find include file " . $titleText. " *** Please make sure that this file exists! ");
      }
      $template  = $myArticle->getPage()->getContent()->getNativeData();  // the data in MediaWiki:ParsifalTemplate/$shortName
      $text = str_replace ($titleText, $template, $text);
    }
   return $text;
  }  
    
    
  public static function cleanupParsifalCache () {
    $VERBOSE = false;      
    TeXProcessor::debugLog ("\n\n cleanupParsifalCache called\n" );
    
    $set        = new Ds\Set();          // collect all the hashes we might see
    $extensions = new Ds\Set ();         // collect all the extensions we might see
    if (is_dir(CACHE_PATH)) {
      TeXProcessor::debugLog ("cleanupParsifalCache: CACHE_PATH is a directory \n" );
      if ( $dh = opendir(CACHE_PATH) ) {
        if ($VERBOSE) {TeXProcessor::debugLog ("cleanupParsifalCache: opened CACHE_PATH as a directory \n" );}
        while (($file = readdir($dh)) !== false) {
          if ($VERBOSE) {TeXProcessor::debugLog ("Parsifal::cleanupParsifalCache: sees: " . $file. " \n");}
          $fileHash = substr ($file, 0, 32);
          $ext = substr ($file, 32);
          if ( strlen ($ext > 0)      ) {$extensions->add ($ext); }
          if ( strlen ($fileHash) > 3 ) {$set->add ($fileHash);   }  // exclude directories . and .. from being added          
        }

      TeXProcessor::debugLog ("cleanupParsifalCache: set obtained contains elements: " . $set->count() . " \n" );        
      TeXProcessor::debugLog ("cleanupParsifalCache: closing CACHE_PATH as a directory \n" );  
      closedir($dh);
      }
      else {
        TeXProcessor::debugLog ("cleanupParsifalCache: could not open CACHE_PATH directory \n" );
      }
    }
    else {
      TeXProcessor::debugLog ("cleanupParsifalCache: CACHE_PATH is not a directory \n" );
    }
    
    // remove all used 
    $dbl = MediaWikiServices::getInstance()->getDBLoadBalancer();
    $dbr = $dbl->getConnectionRef ( DB_REPLICA );
    
    /* starting from 1.35
    $res = $dbr->newSelectQueryBuilder()
      ->select ( [ 'pp_value' ] )
      ->from   ( 'page_props' )
      ->where  ( ["pp_propname" => 'ParsifalHashsUsed'] )
      ->caller( __METHOD__ )
      ->fetchResultSet();     
    TeXProcessor::debugLog ( "cleanupParsifalCache: iterating result set \n");
    */
    
    // before 1.35
    $res = $dbr->select('page_props', [ 'pp_value' ],  'pp_propname ="ParsifalHashsUsed"',  __METHOD__, [ ] );
    
    foreach ( $res as $row ) {
      $hashArray = unserialize ($row->pp_value);
      foreach ($hashArray as $item) {
        TeXProcessor::debugLog ( "cleanupParsifalCache: removing used hash: " . $item . " \n") ;
        $set->remove ($item);
      } 
    }
    TeXProcessor::debugLog ("cleanupParsifalCache: set obtained, after removal, contains elements: " . $set->count() . " \n" );        
    
    foreach ( $set as $elem ) {
      TeXProcessor::debugLog ("removing for hash " . $elem . " \n");
      foreach ( $extensions as $ext) {
           TeXProcessor::debugLog ("unlinking " . $elem . $ext . " \n");
        unlink ($elem . $ext);
        
      }
    }
    
//    $propValue = $dbr->selectField( 'page_props', 'pp_value', [ 'pp_propname' => "ParsifalHashsUsed" ], __METHOD__ );
//    $propValue = unserialize ($propValue);  
//    $initialHashsUsed = $propValue;
//    if ($VERBOSE) {TeXProcessor::debugLog ("Parsifal::onParserFirstCallInit sees ParsifalHashsUsed: " . print_r ($initialHashsUsed, true). " \n");}      
  
  } // end function  
    
} // end class
    
  

// TODO: DEPRECATE
// function formatException ($e) {return "<h3>A processing exception occured in file " . $e->getFile() . " in line " . $e->getLine() . "</h3>".htmlspecialchars ($e->getMessage()); }

?>