<?php


/** The Decorator class provides various forms of wrapping for a Parsifal produced image or canvas tag.
 *
 *  RATIONALE: A separate class provides a more maintainable approach to the various forms of wrapping we might have.
 *             Decorator provides the methods for decorating, wrapping etc.
 *             The caller tells decorator, what to do.
 *
 */


class Decorator {

  private $kern;           // the kernel which shall be decorated here
  private $content;        // the content as it has been decorated thus far (this is amended throughout the process)
  private $width;          // width of the image
  private $height;         // height of the image

  private $markingClass;   // classes to be added to parsifalContainer

  function __construct ($kern, $width, $height, $markingClass) { $this->kern = $kern; $this->content = $kern; $this->width=$width; $this->height = $height;
     $this->markingClass = "parsifalContainer ".$markingClass;
    }

  public function wrapDivX () {
    $annotations ="";
    $errorInjector ="";

    $this->content =  "<div class='".$this->markingClass."' data-src='wrapDiv'>" 
                      .$this->kern 
                      ."<div class='annoLayer'>".$annotations."</div>"
                     .$errorInjector
                      ."</div>";  
    return $this;
  }


  // the first decoration step MUST be wrapping.
  // wrapping places the image or canvas or whatever kernel together with annotations into a wrapping DIV parsifalContainer
  // This container by position:relative is responsible for proper positioning of the annotation layer
  // CAVE: If we drop this wrapper then mediawiki feels the urge to pack the images into <p> tags which we do not want as it breaks the floating of collapsible handles into one line
  //
  //  $annotations    null or HTML string of the annotations layer
  //                  if string (even if empty) add an annotation layer with this contents
  //                  null:  do not add an annotation layer at all
  public function wrap ( ?string $annotations = "", string $softError = "", string $errPath = "", string $titleInfo ="", string $hash="") {
    global $wgScriptPath;

    // ERROR INFORMATION
    if ($softError === "") {

// For some strange reason this MUST not contain leading blanks or the Mediawiki postprocessor wraps this in <pre>...</pre> tags
      $errorInjector = <<<HEAD
<span class='logWrap'>
<a href='$errPath' onclick='PRT.showAsWin(this);event.preventDefault();' title='Click for tex log in popup'>&#8689;</a>
</span>
HEAD;

    }
    else {

// For some strange reason this MUST not contain leading blanks or the Mediawiki postprocessor wraps this in <pre>...</pre> tags
      $errorInjector = <<<HEADER
<span class='errorWrap'>
<a href='$errPath' class='miniError' onclick='PRT.showAsIframe(this);event.preventDefault();'  title='Click for details in iframe' >$softError</a><br>
<a href='$errPath' class='winError'  onclick='PRT.showAsWin(this);event.preventDefault();'     title='Click for details in popup' >&#8689;</a>
</span>
HEADER;
    }

    if ($this->height == 0) {$this->height=100;}  // emergency fix since this might happen sometimes and then we want some stuff to display so we get at least the link to the log file
    $aspect = $this->width / $this->height;
    $width = $this->width;  $height=$this->height;    ////// TODO: is this required for proper interpolation ????



    $this->content =  "<div class='".$this->markingClass."' $titleInfo style=\"aspect-ratio: $aspect\">" 
                      .$this->kern 
//
// THIS BELOW WORKS FOR HTML
 //                     .( $annotations === null ? "" : "<div class='annoLayer'>".$annotations."</div>")
                        .( $annotations === null ? "" : "<svg width='100%' viewBox=\"0 0 $width $height\" style=\"position:absolute;top:0px;left:0px;aspect-ratio: $aspect;\" preserveAspectRatio='xMidYMid meet'>".$annotations."</svg>")
//
                      .$errorInjector
                      ."</div>"
      ."<script> PRT.patchParsifalEditLinks (document.currentScript);</script>"
    ;  
    return $this;
  }



  // choses the suitable collapsible wrapper and calls the implementation function
  // $ar              the attribute array of the XML tag
  // $softError       an error indication, which could optionally be used for placing some indicator into the (especially: closed) collapse button to suggest the presence of an error   // TODO: the caller does not set this yet properly 
  public function collapsible ( $ar ) {
    if (count ($ar) == 0) {return;}  // no attributes at all, so also no collapse

    foreach ( $ar as $key => $val ) {
      // attribute  o-*  as in  o-proof,  o-definition  or similar  for open and in c-proof etc for closed collapsibles
      if ( str_starts_with ($key, "o-") ) { 
        if (array_key_exists( substr ($key,2), ATT2NAME)) {$name = ATT2NAME[ substr($key,2) ];} else { $name = ucfirst (substr ($key,2)); }
        return $this->wrapCollapsible ( $name, true );   }
      if (str_starts_with ($key, "c-") ) { 
        if (array_key_exists( substr ($key,2), ATT2NAME)) {$name = ATT2NAME[ substr($key,2) ];} else {$name = ucfirst (substr ($key,2)); }
        return $this->wrapCollapsible ( $name, false );  }
      if (strcmp ($key, "o") == 0)       { return $this->wrapCollapsible ( $ar[$key],       true );   }
      if (strcmp ($key, "c") == 0)       { return $this->wrapCollapsible ( $ar[$key],       false );  }
    }
  }




// $label    string              label to be used in the button for state switching   (eg:  Proof,  Remark,  Definition  etc.)
//           empty string        show roundish button without text
//           null                show roundish button without text
//           only white space    show roundish button without text

  public function wrapCollapsible ( ?string $label="Collapsed" , bool $open = true, $errorMarker = false) {
    $size           = BASIC_SIZE + 10;   
    $styleIni       = ( $open ? "display:block;" : "display:none;") . "width:$size px;";                                                                                             // initial styling of the collapsible  
    $styleBtn       = (array_key_exists ($label, ATT2STYLE_SPEC) ? ATT2STYLE_SPEC[$label] : ATT_DEFAULT_STYLE_SPEC ) . "display:inline-block; position:relative;"; 
    if ( $label === null || strlen (trim($label)) == 0) {$labelBtn = "&nbsp;";}

   
    $this->content  =  "<div onclick='toggleNext(event);' title='Click to toggle visibility; with shift for multiple selection' class='collapseButton' style='$styleBtn'><a href='' onclick='collapseAnchor(event);'>$label</a></div>".
                      "<div onclick='toggleImg(event);' style='$styleIni' class='collapseResult'>".$this->content."</div>";
  return $this;
  }


  public function getHTML () : string { return $this->content;}

  // add some more style elements to the IMG tag, depending on the attributes of the latex tag
  static public function addStyle ( $ar, &$style ) {
      if ( array_key_exists ("b", $ar) )      { $style .= "border:1px solid gold;";            }     // add a border
      if ( array_key_exists ("br", $ar) )     { $style .= "border-radius:5px;";                }     // add a border radius
      if ( array_key_exists ("bs", $ar) )     { $style .= "box-shadow: 10px 10px lightgrey;";  }     // add a box shadow
      if ( array_key_exists ("style", $ar) )  { $style .= $ar["style"];            }             // add custom style for the img tag
  }




}  // end class