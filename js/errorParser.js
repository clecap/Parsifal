  

// input: <txt>    a string of LaTeX error messages using our specific formatting
//        <name>   the name of the tex file including the full filesystem path on the server as it is used within LaTeX
// output: an array of error information Objects
//   .core        element belongs to the area of core interest
//   .txt         array of text chunks
//   .lineNum     line number of the error
//   .offending   offending code found





function errorParser (txt, name) {
  var idx;
  var arr = [];
  var pastMarker = false;
  let lineNum = -1;        // the lineNum we currently have;  -1 indicates that we have identified NO lineNumber


  let obj = {};
  obj.txt = [];       // array of individual text chunks    
  obj.lineNum = -1;
  arr.push (obj);      // top arr always is the thing we write into
  let top = arr[arr.length-1];  // get the last element written to the array

  txt.split("\n").forEach ( ele => {                                     // split error messages into individual lines ele
    // detect the status 
    if ( ele.indexOf (ERROR_PARSER_START) != -1 ) { pastMarker = true;  }  // line consists of the HERE_MARKER

    if ( ele.indexOf (ERROR_PARSER_END) != -1 )   { // we detected a switch past the error parser end marker    
      pastMarker = false; 
      obj = {};
      obj.txt = [ele];
      lineNum = -1;
      obj.lineNum = lineNum;
      arr.push (obj);
      top = arr[arr.length-1];
      return;  // do not register this line twice (here and below)
     }  // line consists of the HERE_MARKER

    // find a line number
    //console.info ("SEEKING: " + name + ".tex:");
    // console.info ("IN: " + ele);
    if (pastMarker) {
    if ( (idx = ele.indexOf ( ".tex:"   )) != -1 ) {              // found a file - line identification line
    //  console.info ("FOUND a line identification line");
      idx2 = ele.lastIndexOf (":");
      obj = {};
      obj.txt = [ele];
      lineNum = ele.substr ( idx+5 );   // pick up line number string
      lineNum = parseInt (lineNum);     // extract numerical line number
      obj.error = ele.substr (idx2+2);
      obj.lineNum = lineNum;
      obj.core = true;
      arr.push (obj);
      top = arr[arr.length-1];
    }
    else { // we did not get a line number, so stay with the current element
      top.txt.push (ele);

      const REG = /^l\.\d+\s+/;
      if ( REG.test (ele) ) {top.offending = ele}


    }
    }
    else {
      top.txt.push (ele);
    }  

  } );
  return arr;
}


function errorFormatter (arr) {
  var logContainer = document.getElementById ("logContainer");  // the container into which we fill in the info
  // console.log ("errorFormatter got: ", arr);

  arr.forEach ( (obj) => {
    // console.info ("Individual Error object ", obj);

    logContainer.appendChild ( document.createElement ("hr"));
    let objDiv = document.createElement("div");

     if (obj.lineNum != -1) {
       let lineInfo = document.createElement ("h3");
       lineInfo.appendChild (document.createTextNode ( "LINE " + obj.lineNum + " ERROR: " + obj.error + "")); 
       objDiv.appendChild (lineInfo);    
     }


    obj.txt.forEach ( (chunk) => {
     // console.warn ("Text chunk ", chunk, chunk.length, typeof chunk);  
      let chunkDiv = document.createElement ("div");
      chunkDiv.appendChild ( document.createTextNode (chunk));
      objDiv.appendChild (chunkDiv);
    });

    objDiv.className = (obj.core ?  "impErr" : "err" );
 
    
    logContainer.appendChild ( objDiv );
  } );

  accFormatter (arr, true);

}


// List of HTML entities for escaping.
var htmlEscapes = {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#x27;', '/': '&#x2F;'};

// Regex containing the keys listed immediately above.
var htmlEscaper = /[&<>"'\/]/g;

// Escape a string for HTML interpolation.
const htmlEscape = (string) => ('' + string).replace(htmlEscaper, function(match) {return htmlEscapes[match];} ) ;  


// format an accordion from the array arr of error objects
// if skip is true: skip the elements with negative line number
// 
function accFormatter (arr, skip) {
  const accContainer = document.getElementById ("accContainer");

  arr.forEach ( (obj) => {
    if (skip && obj.lineNum == -1) {return;}
    let txt = ""; obj.txt.forEach ( (chunk) => { txt += "<div>" + htmlEscape (chunk) + "</div>" } );
    accContainer.innerHTML += `
      <button class="accordion"><b>${obj.lineNum}: ${obj.error}</b> ${ (obj.offending ? obj.offending : "") }</button>
      <div class="panel"><p>${txt}</p></div>
     `;
  });
}



window.errorParser = errorParser;
window.errorFormatter = errorFormatter;



