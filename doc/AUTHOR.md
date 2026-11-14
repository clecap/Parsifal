# Author Instructions

## XML-Tag

The choice of the XML tag affects the LaTeX format.

`<block>`  implements a simple fixed width block 
 



 
## XML-Attributes

The XML attributes of the tags may be used to invoke additional effects. The key of the attribute should be specified
in lowercase, but uppercase attribute keys are also recognized; they will be lowercased by the Mediawiki parser.

## Headings

Mediawiki headings start with one or more =, are followed by the section heading and close with the same number of = as they started.

To prevent parser confusion, Mediawiki headings MUST not be part of a LaTeX text.
Thus, in LaTeX you must avoid starting a line with an equal sign and ending it with an equal sign as well.

If you violate this rule, the preview view in editing mode might not show the previews as expected.

This is not a bug but a feature which enables us to do fast edit previews.



## Choice of LaTeX Template and Preamble

* attribute f is present:  use file as template (else use MediaWiki: as template)

## Width of Rendered Image
* 2) Width of rendered image
* Width of image: 
* narrow
* (none)
* wide


### Collapsible editOptions
 
   Open and closed variants with preconfigured color.
 
  onotation
  cnotation
  oproof
  cproof
  oremark
  cremark
  oexample
  cexample


### Border Decoration
  * 3) Border around rendered image
  * attribute b is present:  draw a border around the image
  
### Render Type 
 
   * 
   * 4) Render type:  ** TODO !!
   * i   image        (if i has no value, then 300 dpi resolution are used, else specify resolution in the value of i)   
   * p   pdf
   * 
  
   * 5) Collapsible portion
   *  c="Remark"   make it a collapsible remark
   *  c="Proof"    make it a collapsible proof
   * 
   * 6) Hoverable portions
   *  h="Remark"
   
   
   *  7) DPI setting   d=300  (default)
   

### co Collapsible Open

The Latex content is shown but can be collapsed by a click on the button.
If the attribute has no value: Add a button with the text "Collapse".
If the attribute has a value:  Add a button with the given value.

### cc Collapsible Closed

The Latex content is not shown but can be collapsed by a click on the button.

### c collapsible

Mark the area as collapsible with a click on the area.

### o outlined

Forces an outline on the image. Helpful for debugging and adjusting distances.


# Attributes

* ```sans```  Use sans serif fonts
* ```double```````
* ocg
* rerun



## LaTeX Typesetting

## Using Links

  \url{}
  \url{file:}



## Hints

Using hint content inside of a Latex document:

\hinturl{hintKey} where url is an arbitrary Internet url of a PNG resource. It is displayed as the url itself,
covered by an HTML element which, when hovered, displays the PNG resource.

\hintref{hintKey}{latexMaterial} is displayed as the latexMaterial

\hint{hintKey} where hintKey is the key of a hint

\hintnote{latexMaterial} generate a footnotemark like mark on the preceeding text which on hovering
displays the latexMaterial

\citenote{latexMaterial} generates a citation-key like number which on hovering displays the latexMaterial



Providing hint content:

* As visible part of a Mediawiki page.
  The hint belongs to a Mediawiki page and is written there. We access it via http://<pageUrl>#<Section>?<optionalNumber>

* As invisible part of a Mediawiki page, when the hint semantically belongs to this page
  but should not become visible on this page.

* As a Mediawiki page of its own.
Generate a Mediawiki page just 

### Hints Author Interface

Shift-Ctrl   toggles additional information elements for the author

### Hints PDF Interface



# Internal Hint Design Decisions:

* An author generates the hints on a normal Wiki page. and may provide a tag with an attribute to provide a name which must be unique on this Wiki page.

  
      
              * The hint can be referenced by providing the page title plus the hint name.
* API need: We need an endpoint with input: pageTitle, hintName and output: hashOfTag for referencing the hint.

* We need a mechanism how an author can find out that he is about to destroy / rename / move content which serves as target of a hint linking.
* API need: We need an endpoint with input: hashOfTag and output: list of Wiki pages referencing this hash 
** we need this as warning to user who might be changing the name of a referenced element and should see this while doing so !

Idea: When we have a page providing a named element, then we automatically create a subpage or seperate page which contains the cooked contents (img tag)
and which is identified by the full name of the hint. 
Hints are referenced by referencing the url of this special hint page.
The special hint page can be changed as such - and the hint page will always know from where it originally came from. 
What if the origin is changed?




# Authoring Templates

## Using Latex input and include

The system is configured that with \input{test.tex} it loads the latex file in the ./local directory.

Since file loading may increase preview latency it is not recommended to be use it heavily.

* block
* function for toggeling the table of contents




















