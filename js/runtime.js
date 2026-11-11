
//console.error ("Parsifal runtime.js has started to load");





/* display an image only after a complete load: prevents that user sees partially loading image from a slow loading process; may be called in img tag of rendered html produced by PHP */



/*** COLLAPSIBLES of PARSIFAL ***/
// #region
// defines a function which is used by <script> tags injected in TexProcessor for rendering collapsibles
// called when user clicks on a collapsing button
window.toggleNext = function toggleNext (e) { 
  console.log ("parsifal: toggleNext", e);
  if (e.target.tagName == "A") {button = e.target.parentNode;} else {button = e.target;}

  const ele    = button.nextSibling;   
  $(ele).slideToggle(300);                               // slide open  // TODO: put to more prominent and thus better visible and adjustable place 

// trying to get it done without jquery since we do not have that loaded now !
 // ele.style="overflow: hidden; transition: all 300ms ease-in;"
 // if (ele.style.display=="none")  { ele.style.display="block"; ele.style.height = "100px";  }
 // if (ele.style.display=="block") { ele.style.height = "0px";}

  button.classList.toggle ("collapseButtonToggled");     // show button markup to better identify buttons of open areas
  if (!e.shiftKey) {                                     // no shift: close all the others
    $(".collapseResult").not(ele).hide();
    //let elems = document.querySelectorAll (".collapseResult"); for (var i = 0; i < elems.length; ++i) { if (elems[i] != ele ) { elems[i].style.display="none"; } }

    $(".collapseButtonToggled").not(button).removeClass ("collapseButtonToggled");
   // elems = document.querySelectorAll (".collapseButtonToggled"); for (var i = 0; i < elems.length; ++i) { elems[i].classList.remove ("collapseButtonToggled"); }

  }  
}


window.toggleImg = function toggleImg (e) { 
  const ele = e.currentTarget; 
  const button = e.currentTarget.previousSibling;
  console.log ("button: ", button); console.log ("element: ", ele);  
  $(ele).slideToggle(150);
  button.classList.toggle ("collapseButtonToggled");
}


// allows a collapsible to be opened with right-click in seperate window etc.
window.collapseAnchor = function collapseAnchor (e) {
  if (e.button!=2) { e.preventDefault();}
}



// #endregion


window.PRT = PRT = (() => {

// what is our load path? necessary to have in the mediawiki and endpoint situations always the correct path available
let myPath=document.currentScript.src;
let lastIndex = myPath.lastIndexOf ("/");
myPath = myPath.substring (0,lastIndex);




const fixDistances = () => { 
  let parsis = document.querySelectorAll (".parsifal");
  console.warn ("parsis is ", parsis);
  parsis.forEach(function(element) {
    // console.log (element, element.dataset.strut);
    let ele = element; 
    while (ele && ele.classList && !ele.classList.contains ("parsifalContainer")) { 
     if (ele && ele.classList && ele.classList.contains ("collapseResult")) { break;}
     ele = ele.previousElementSibling;}
    
    if (ele && ele.classList && ele.classList.contains ("parsifalContainer") ) {ele.classList.add (element.dataset.strut);}
    element.parentNode.removeChild (element);
  });

  parsis = document.querySelectorAll (".parsifal");
  //console.warn ("parsis remaining are ", parsis);
};

document.addEventListener('DOMContentLoaded', () => {
  fixDistances();
});




const toggleLimitSize = () => {
  let sizeLimit = window.localStorage.getItem ("Parsifal-size-limit") === "true";  
  limitSize ( !sizeLimit);
};

const limitSize = (flag) => { 
  window.localStorage.setItem ("Parsifal-size-limit", (flag ? "true" : "false"));
  implementLimitedSize();
};

const implementLimitedSize = () => {
  let sizeLimit = window.localStorage.getItem ("Parsifal-size-limit") === "true";  
  if (sizeLimit) { document.documentElement.classList.add     ("sizeLimit"); }
  else           { document.documentElement.classList.remove  ("sizeLimit");}
};



const toggleEditHandles = () => {
  let editHandlesVar = window.localStorage.getItem ("Parsifal-edit-handles") === "true";  
  editHandles ( !editHandlesVar);
};

const editHandles = (flag) => { 
  window.localStorage.setItem ("Parsifal-edit-handles", (flag ? "true" : "false"));
  implementEditHandles();
};

const implementEditHandles = () => {
  let editHandles = window.localStorage.getItem ("Parsifal-edit-handles") === "true";  
  if (editHandles) { document.documentElement.classList.add     ("editHandles"); }
  else           { document.documentElement.classList.remove  ("editHandles");}
};



const togglePreview = () => {
  let previewFlag = window.localStorage.getItem ("Dante-preview-flag") === "true";  
  setPreviewSetting ( !previewFlag);
};

const setPreviewSetting = (flag) => {
  window.localStorage.setItem ("Dante-preview-flag", (flag ? "true" : "false"));
  implementPreviewSetting ();
};

const implementPreviewSetting = () => {
  let previewFlag = window.localStorage.getItem ("Dante-preview-flag") === "true";  
  if (previewFlag) { document.documentElement.classList.add     ("previewFlag"); }
  else             { document.documentElement.classList.remove  ("previewFlag");}
};


const toggleVariants = () => {
  let showingVariants= window.localStorage.getItem ("Parsifal-show-variants") === "true";  
  showVariants ( !showingVariants);
};

const showVariants = (flag) => { 
  window.localStorage.setItem ("Parsifal-show-variants", (flag ? "true" : "false"));
  implementShowVariants();
};

const implementShowVariants = () => {
  let showingVariants = window.localStorage.getItem ("Parsifal-show-variants") === "true";  
  if (showingVariants) { document.documentElement.classList.add     ("showVariants"); }
  else                 { document.documentElement.classList.remove  ("showVariants");}
};





const toggleCodeMirror = () => {
  let usingCodeMirror = window.localStorage.getItem ("Dante-use-codemirror") === "true"; 
  usingCodeMirror = !usingCodeMirror; 
  window.localStorage.setItem ("Dante-use-codemirror", (usingCodeMirror ? "true" : "false"));
  implementCodeMirror();
};

const implementCodeMirror = () => {
  let usingCodeMirror = window.localStorage.getItem ("Dante-use-codemirror") === "true";  
  console.error ("using code mirror value is: ", usingCodeMirror);
  if (usingCodeMirror) { document.documentElement.classList.add     ("usingCodeMirror"); }
  else                 { document.documentElement.classList.remove  ("usingCodeMirror");}
};



// patches the edit section links before a parsifal container
// called for every parsifal container via php injected script tag line
const patchParsifalEditLinks = (sc) => {
  //console.info ("runtime.js: patchParsifalEditLinks: ", sc);
  var pc       = sc.previousSibling;
  //console.info ("parsifalContainer: ", pc);

  pc.addEventListener ("mouseenter", (e) => { if (e.shiftKey) {pc.style.outline = "1px dotted grey";} } );
  pc.addEventListener ("mouseleave", (e) => { pc.style.outline = ""; } );
  return;

    var hElement = pc.previousSibling;
    while ( hElement && !["H1","H2","H3","H4","H5","H6"].includes (hElement.tagName)) { hElement = hElement.previousSibling;}
    //console.info ("h-element", hElement);
    var err = pc.querySelector (".errorWrap");
    //console.info ("err", err);
    var es = hElement.querySelector (".mw-editsection");
    //console.info ("editsection", es);
    es.parentNode.removeChild (es);
    err.appendChild (es);
};


// when this function is called on an element ele then the hash id of the image contained in this element
// is broadcast 
const broadcastPosition = (ele) => {
  let danteBC = new BroadcastChannel ("danteBC" );
  let img = ele.querySelector ("img.texImage");
  let id  = img.id; 
  danteBC.postMessage (  {"positionAtId": id } );  
};



const showAsIframe = (url) => {   // console.log (" show " + url + " as iframe inside of the current window", url);
  let frame =  document.getElementById ("errorIframe");
  // console.warn (frame);
  if (frame) {return;}  // already showing
  frame = document.createElement ("iframe");
  frame.src=url;
  url.parentNode.style.display="none";
  frame.id="errorIframe";
  frame.style = "position:fixed; top:10px; left:10px; width:800px; height:800px; overflow:scroll;background-color:white;";
  document.body.appendChild (frame);
};

const showAsWin = (url) => { //  console.log (" show " + url + " as iframe inside of the current window");
  const handle = window.open( url, "errorWindow", "left=1,top=1,width=800,height=800");
};


// find all comment nodes - currently not used but keep in as idea
/*
function getComments (root) {
  var treeWalker = document.createTreeWalker ( root, NodeFilter.SHOW_COMMENT, {"acceptNode": function acceptNode (node) { return NodeFilter.FILTER_ACCEPT; } } );
    // skip the first node which is the node specified in the `root`
    var currentNode = treeWalker.nextNode();
    var nodeList = [];
    while (currentNode) { nodeList.push(currentNode); currentNode = treeWalker.nextNode(); }
    return nodeList;
}
*/

return ( {toggleLimitSize,   limitSize,         implementLimitedSize, 
         togglePreview,     setPreviewSetting, implementPreviewSetting,
         toggleVariants,    showVariants,      implementShowVariants ,
         toggleEditHandles, editHandles,       implementEditHandles,
         toggleCodeMirror,                     implementCodeMirror,
         showAsIframe, showAsWin, 
         patchParsifalEditLinks});    // export functions to the PRT Parsifal Run Time object

})();  // END of object PRT definition


// code to debug the multiple load problem (which now seems to be solved)
// if (typeof window.PRTLC === "undefined") {console.error ("first time loaded"); window.PRTLC = 1;} else {console.error ("multiple times loaded - why??");}

PRT.implementLimitedSize();
PRT.implementEditHandles();
PRT.implementCodeMirror ();

// console.error ("Parsifal runtime.js has loaded successfully");
//console.log ("post Parsifal loader: ", Object.keys (mw.loader));
//setTimeout ( () => { console.log ("post post Parsifal loader: ", Object.keys (mw.loader));}, 2000 );
