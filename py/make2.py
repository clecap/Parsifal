#!/usr/bin/python

# call this with: $scale    $CACHE_PATH$hash$inFinal   $CACHE$hash$outFinal


import pymupdf as fitz   # PyMuPDF; the "fitz" module name is deprecated
import sys
import math  

# print ("Found argv ", len(sys.argv))

if len(sys.argv) != 4 :
  print("use:  scale-parameter in-path out-path")  
  exit(-1)

# print ("found script args: scale=" + sys.argv[1] + " in-path= " + sys.argv[2] + " out-path= " + sys.argv[3] );
  
scale        = float(sys.argv[1]);
pdfFileName  = sys.argv[2] + ".pdf";
pngFileName  = sys.argv[3] + ".png";
htmlFileName = sys.argv[3] + ".html";
svgFileName  = sys.argv[3] + ".svg";



#print(fitz.__doc__)
doc = fitz.open(pdfFileName)
page = doc.load_page(0)               # loads page number 0

ocgLayers = doc.layer_ui_configs()
ocgNum    = len(ocgLayers)
if ( ocgNum > 0) :
  print ("Having " + str(ocgNum) + " OCG layers")
  index = 0
  for item in doc.layer_ui_configs():     # for all the ocg layers we know
#    print (item)                          # print the layer
    doc.set_layer_ui_config(index, action=2)  # switch number index to status 2=OFF 

    matrix=fitz.Matrix(scale,scale)        # Matrix (2,2) for 2 zoom    mat = fitz.Matrix(zoom_x, zoom_y) 
    pix = page.get_pixmap(matrix=matrix)
    pix.save(pngFileName)                   # store image in a file
    index = index + 1
else:
#  print ("only one content layer");
  matrix=fitz.Matrix(scale,scale)        # Matrix (2,2) for 2 zoom    mat = fitz.Matrix(zoom_x, zoom_y) 
  pix = page.get_pixmap(matrix=matrix)
  pix.save(pngFileName)                   # store image in a file

#print ("\n")
#print ("Annotations")

# for annot in page.annots():
#  print (annot) 

linksHtml = "";  # collecting the A tags for the links
annosHtml = "";  # collecting the A tags for the hint annotations
imgHtml   = "";  # collecting the IMG tags for the hint annotations
scripts   = "";  # collecting the SCRIPT tags for the A - IMG connections  
arr       = [];  # stores the IMGs we already have rendered so that we only render images once

index = 0
for link in page.links():
  #print (link)
  #print (link["from"])
  #print (link["from"][0])

  left   =  math.ceil(scale * link["from"][0])
  top    =  math.ceil(scale*link["from"][1])
  width  =  math.ceil (scale* (link["from"][2]-link["from"][0]) ) 
  height =  math.ceil (scale* (link["from"][3]-link["from"][1]) ) 
    
  #beauty injections: we will add a bit of slack to make the font line nicer
  height += 2
  width += 4
  top -=1
  left -= 1

  if ( link["uri"][0:6] != "frame:" ):   # if it is not a frame: link
#    //  THIS BELOW WORKS FOR HTML ALONE
#    //  linksHtml += "<a target='_blank' data-id='a" + links[i].uri + "' style='top:"+top+"px;left:"+left+"px;width:"+width+"px;height:"+height+"px;position:absolute;cursor:pointer;' class='pdf-external-anchor' href='" + (links[i].uri) + "' title='Open external link in new tab: " + links[i].uri  +"'></a>";##
#
#   // TEST
#   // TEST   linksHtml += "<a target='_blank' data-id='a" + links[i].uri + "' style='top:"+top+"px;left:"+left+"px;width:"+width+"px;height:"+height+"px;position:absolute;cursor:pointer;' class='pdf-external-anchor' href='" + (links[i].uri) + "' title='Open external link in new tab: " + links[i].uri  +"'></a>";
    linksHtml = linksHtml + "<a target='_blank' data-id='a" + link["uri"] + "' xlink:title='Open external link in new tab: " + link["uri"] + "' xlink:href='"+ link["uri"] + "'><rect x='"+ str(left) +"' y='"+ str(top)+"' width='"+ str(width)+"' height='"+str(height) + "' style='fill:blue;fill-opacity:0.1;stroke-width:0;stroke:blue;' /></a>"




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


with open(htmlFileName, "w") as text_file:
    print("<!-- %s -->" % scale)
    print("%s" % linksHtml)
    print("%s" % annosHtml)
    print("%s" % imgHtml)
    print("%s" % scripts)