The extension fires on edit / save (in the parser).

Solution: If we PURGE the page then mediawiki redoes the edit cycle itself. So purge with php maintenance script or in cron or via
an extension which does that via an admin button.



## Coding Alternative

We could also code this in a way where we first determine all data necessary for the Wiki to Html translation and only lateron generate 
files which are necessary. For example, the HTML based annotation layer could be generated later.

We do not do this now, since we found a fast way to generate both at the same time.

However, a working code portion for this concept would be:

```
if ($areWeDone) { // all compilations we eventually might require have now run
  $version="final";
}
else {  
   //self::debugLog (" WE ARE STILL MISSING stuff for $hash, setting cache expiry to 0\n");                                                                                  // we still lack PNG or HTML, but can move ahead since we know the hash of the TeX source
   $version="prelim";                                                                     // mark the image as preliminary (this is then picked up in style file latex.css via data-prelim)
   $parser->getOutput()->updateCacheExpiry(0); $wgOut->enableClientCache( false );        // disallow caching for this version; this ensures we trigger another call of the parser upon next display (where then this issue will be fixed)
//    self::completeInBackground ($hash);                                                   // kick off completion of all details which then will be available and used upon the next display
}
 if ($VERBOSE) {self::debugLog ("  cache expiry time: " . $parser->getOutput()->getCacheExpiry(). "\n" );}
``` 


## Library Access 

### Goals 

* We want to connect Mediawiki texts directly to references.
** Example 1: We have a DoI 
** Example 2: We have a URL 
** Example 3: We have a file and are allowed to provide it to others
** Example 4: We have a file and are not allowed to provide it to others 


### Use Case:

* When writing, we are likely to have the file open.
* We drop the file on a specific area of the edit page
** We get the SHA-1 hash, copyable to clipboard.
** Optional: We get the file displayed. 
** We register file meta data in a database, if not yet done already.
** We store the meta information in the database  

### Database

### References*
*  https://www.mediawiki.org/wiki/Manual:Schema_changes

References:
* https://github.com/wikimedia/mediawiki-extensions-Push/










