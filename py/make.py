#!/opt/myenv/bin/python

# Call this with: $scale  $inpath  $outpath   (in the caller TeXProcessor::Pdf2PngHtmlMT it is: $scale  $CACHE_PATH$hash$inFinal  $CACHE_PATH$hash$outFinal)
#
# Reads    $inpath.pdf  (only page 0 is used)
# Produces $outpath.png   page 0 rendered with zoom factor $scale
#          $outpath.html  SVG <a><rect/></a> elements for the links of page 0; Decorator.php places them verbatim into an <svg> on the wiki page
#
# On success: exit code 0, stdout receives "<width> <height>" of the PNG in pixels
# On error:   exit code 1, stdout is empty, stderr receives a line starting with "make.py ERROR:" which TeXProcessor::Pdf2PngHtmlMT reports
# Debug info is written to stderr
# Both output files are written atomically (temp file plus rename), so a reader never sees a partially written file

import pymupdf
import sys
import os
import math
import html
from urllib.parse import urlsplit


ALLOWED_SCHEMES = ("http", "https", "mailto")   # link protocols which may appear in the page; relative links (no scheme, no host) are permitted as well

doc = None                                      # the open PDF document, closed by fail() or at the end


def fail (msg):
  """Report an error to the caller (PHP) via stderr and exit code 1, closing the document if it is open.

  :param msg: error description
  """
  print("make.py ERROR: " + msg, file=sys.stderr)
  if doc is not None: doc.close()
  sys.exit(1)


def renderPng (page, scale, fileName):
  """Render a page with zoom factor scale and store it atomically as PNG.

  :param page:     pymupdf page to render
  :param scale:    zoom factor in both directions
  :param fileName: path of the PNG file to produce
  :return:         the rendered pymupdf.Pixmap
  """
  tmpName = fileName + "." + str(os.getpid()) + ".tmp"
  try:
    matrix = pymupdf.Matrix(scale, scale)              # Matrix (2,2) for 2 zoom    mat = pymupdf.Matrix(zoom_x, zoom_y)
    pix    = page.get_pixmap(matrix=matrix)
    pix.save(tmpName, output="png")                    # explicit format, since the temp name has no .png extension
    os.replace(tmpName, fileName)                      # atomic on the same file system
    return pix
  except Exception as e:
    if os.path.exists(tmpName): os.remove(tmpName)
    fail("could not render " + fileName + ": " + repr(e))


def writeAtomic (fileName, text):
  """Write a text file atomically (temp file plus rename).

  :param fileName: path of the file to produce
  :param text:     content to write
  """
  tmpName = fileName + "." + str(os.getpid()) + ".tmp"
  try:
    with open(tmpName, "w", encoding="utf-8") as text_file:
      text_file.write(text)
    os.replace(tmpName, fileName)
  except Exception as e:
    if os.path.exists(tmpName): os.remove(tmpName)
    fail("could not write " + fileName + ": " + repr(e))


def safeUri (uri):
  """Decide whether a link target may be placed into the page.

  Permitted are http, https and mailto links, and relative links (no scheme, no host) such as wiki links.
  Rejected are all other schemes (javascript:, data:, ...), protocol-relative links (//host/...) and URIs containing whitespace or control characters,
  since browsers drop such characters when parsing a URL, which would let "java\tscript:" pass a naive scheme check.

  :param uri: link target as found in the PDF
  :return:    True if the link may be used
  """
  if any(ord(c) < 0x20 or ord(c) == 0x7f or c.isspace() for c in uri): return False
  try:
    parts = urlsplit(uri)
  except ValueError:
    return False
  if parts.scheme == "": return parts.netloc == "" and not uri.startswith("//")   # relative link: must not point to another host
  return parts.scheme.lower() in ALLOWED_SCHEMES


# print ("Found argv ", len(sys.argv))

# bark with error if we have a different number of args than 3. (compare with 4 since the command is counted as well)
if len(sys.argv) != 4 :
  fail("wrong number of arguments; use:  make.py  scale  in-path  out-path   (paths without extension)")

# print ("found script args: scale=" + sys.argv[1] + " in-path= " + sys.argv[2] + " out-path= " + sys.argv[3] );

# parse and check the scale argument
try:
  scale = float(sys.argv[1])
except ValueError:
  fail("scale is not a number: " + sys.argv[1])
if not math.isfinite(scale) or scale <= 0: fail("scale must be a positive number, got " + sys.argv[1])

pdfFileName  = sys.argv[2] + ".pdf"
pngFileName  = sys.argv[3] + ".png"
htmlFileName = sys.argv[3] + ".html"
svgFileName  = sys.argv[3] + ".svg"


# open the PDF and load its first page
#print(pymupdf.__doc__, file=sys.stderr)
try:
  doc = pymupdf.open(pdfFileName)
except Exception as e:
  fail("could not open " + pdfFileName + ": " + repr(e))
if doc.page_count < 1: fail(pdfFileName + " has no pages")
page = doc.load_page(0)                                # load page number 0
width, height = page.rect.width, page.rect.height

# print ("page loaded, bounds are: ", page.bound(), file=sys.stderr)


ocgLayers = doc.layer_ui_configs()
ocgNum    = len(ocgLayers)
if ( ocgNum > 0) :
#  print ("Having " + str(ocgNum) + " OCG layers", file=sys.stderr)
  index = 0
  for item in doc.layer_ui_configs():         # for all the ocg layers we know
#    print (item, file=sys.stderr)             # print the layer
    doc.set_layer_ui_config(index, action=2)  # switch number index to status 2=OFF

    pix = renderPng(page, scale, pngFileName)  # store image in a file
    index = index + 1
else:
#  print ("only one content layer", file=sys.stderr)
  pix = renderPng(page, scale, pngFileName)    # store image in a file


#print ("\n", file=sys.stderr)
#print ("Annotations", file=sys.stderr)
for annot in page.annots():
  print (annot, file=sys.stderr)


linksHtml = ""  # collecting the A tags for the links
annosHtml = ""  # collecting the A tags for the hint annotations
imgHtml   = ""  # collecting the IMG tags for the hint annotations
scripts   = ""  # collecting the SCRIPT tags for the A - IMG connections
arr       = []  # stores the IMGs we already have rendered so that we only render images once

index = 0
for link in page.links():
  print ("IN THE LINK LOOP WITH A LINK", file=sys.stderr)
  print (link, file=sys.stderr)

  left   =  math.ceil(scale * link["from"][0])
  top    =  math.ceil(scale*link["from"][1])
  width  =  math.ceil (scale* (link["from"][2]-link["from"][0]) )
  height =  math.ceil (scale* (link["from"][3]-link["from"][1]) )

  #beauty injections: we will add a bit of slack to make the font line nicer
  height += 2
  width += 4
  top -=1
  left -= 1

  uri = link.get("uri")
  if ( uri and not uri.startswith("frame:") ):   # if it is not a frame: link
    if not safeUri(uri):                         # drop links with forbidden protocols, but tell the log
      print ("make.py: dropped link with forbidden target " + repr(uri), file=sys.stderr)
      continue
    escUri = html.escape(uri, quote=True)        # escape for use inside attribute values
#    //  THIS BELOW WORKS FOR HTML ALONE
#    //  linksHtml += "<a target='_blank' data-id='a" + links[i].uri + "' style='top:"+top+"px;left:"+left+"px;width:"+width+"px;height:"+height+"px;position:absolute;cursor:pointer;' class='pdf-external-anchor' href='" + (links[i].uri) + "' title='Open external link in new tab: " + links[i].uri  +"'></a>";##
#
#   // TEST
#   // TEST   linksHtml += "<a target='_blank' data-id='a" + links[i].uri + "' style='top:"+top+"px;left:"+left+"px;width:"+width+"px;height:"+height+"px;position:absolute;cursor:pointer;' class='pdf-external-anchor' href='" + (links[i].uri) + "' title='Open external link in new tab: " + links[i].uri  +"'></a>";
    linksHtml = linksHtml + "<a target='_top' data-id='a" + escUri + "' xlink:title='Open external link in new tab: " + escUri + "' xlink:href='"+ escUri + "'><rect x='"+ str(left) +"' y='"+ str(top)+"' width='"+ str(width)+"' height='"+str(height) + "' style='fill:blue;fill-opacity:0.1;stroke-width:0;stroke:blue;' /></a>"

#  if ( 'file' in link ):
#    linksHtml = linksHtml + "<a target='_blank' data-id='a" + link["file"] + "' xlink:title='Open external link in new tab: " + link["file"] + "' xlink:href='"+  serverPrefix + "/index.php?title="  + link["file"] + "'><rect x='"+ str(left) +"' y='"+ str(top)+"' width='"+ str(width)+"' height='"+str(height) + "' style='fill:blue;fill-opacity:0.1;stroke-width:0;stroke:blue;' /></a>"


#    }
#    else {
#      print ("link " + i + " is a hint link");
#      var localSrc = links[i].uri.substring (6);
#      print ("   localSrc is ", localSrc);
#      annosHtml += "<a data-id='a"+localSrc+"' style='top:"+top+"px;left:"+left+"px;width:"+width+"px;height:"+height+"px;cursor:pointer;position:absolute;' class='pdf-external-hint' href='"+localSrc+"' title='Show hint'></a>";
#      if (arr.indexOf (localSrc) == -1) {  // if not yet rendered
#        imgHtml += "<img id='img"+localSrc+"' src='"+localSrc+"' class='img-hint' style='display:none;position:absolute;'/>\n";
#        scripts += "<script>linkHint('"+localSrc+"')</script>\n";
#        arr.push (localSrc);
#      }
#      print ("done");
#    }
#  } // for#
#
#  print ("links completed");

#  for (var i=0; i<annos.length; i++) {
#    print ("type", annos[i].getType(), " flags=", annos[i].getFlags(), " Contents:", annos[i].getContents(), "Rect:", annos[i].getRect() );
#    var left   =  Math.ceil(links[i].bounds[0]);
#    var top    =  Math.ceil(links[i].bounds[1]);
#    var width  =  Math.ceil (links[i].bounds[2]-links[i].bounds[0]) ;
#    var height =  Math.ceil (links[i].bounds[3]-links[i].bounds[1]) ;
#  }


#  var htmlBuf = new Buffer ();
#  htmlBuf.write ("<!--  " +scale +  "  -->" + linksHtml + annosHtml + imgHtml + scripts);
#  htmlBuf.save (htmlFileName);
#  print ("GENERATED: " + htmlFileName);


# write the annotation layer
writeAtomic(htmlFileName, "<!-- %s -->" % scale + linksHtml + annosHtml + imgHtml + scripts)

doc.close()                                    # done with the PDF
doc = None

print(f"{pix.width} {pix.height}")            # report size only after all files were written successfully, so PHP never gets a size without files
