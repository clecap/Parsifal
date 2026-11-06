# Tex installation

Most likely Parsifal will run with the TeX installation you have on your Linux distribution and which you 
install(ed) with you favorite package manager. If this is the case you can stop reading now.

If it does not, or if you are a TeXnician or if you want to use the most up to date versions of TeX, then read on.

## Which TeX system shall I use?

### Recommendation

It is recommended to run Parsifal with a vanilla TeXLive from TUG / CTAN and not with the Linux distribution tex installations.

### Reasons
* Parsifal *initially did run* with my Debian-packaged TeX installation out of the box and so I did not bother.
* Parsifal *then ran* into problems when I used uncommon fonts, needed the font generation mechanism, when I installed new fonts, 
  required recent versions of macro packages, wanted to experiment with newer TeX developments and more.
* It looks like the TUG / CTAN versions are much more up to date, receive bug fixes and more.
* It looks like the Linux distributions use quite old TeX versions, rarely do updates to them and occasionally use non-standard or not well documented conventions. However on
  the positive side, they are easy to install.

### Decision
* For the above reasons I decided to use the TUG / CTAN installation.
* Further development of Parsifal followed the respective requirements and are documented below.
* I do not know and did not test limitations this decision has for Linux distribution installations of TeX.


## Preparing for Texlive installation: Remove Old Installation

I recommend to remove all existing Linux TeX installations to prevent a mixture between installations, settings and configurations. In my case (Debian) this meant

* Uninstall TeX and related packages
  ```
  sudo apt-get remove texlive-full texlive-fonts-recommended  texlive-common
```
* Delete remaining old configuration files or move them out of the way. In my case (Debian) this meant:
  ```
  sudo mv /etc/texmf /etc/texmv.old
  ```

* You might want to check dependencies with `apt rdepends texlive-full` to make sure you have not missed any package.




# Other aspects

## SECURITY ASPECTS

1 tmp should contain a file .htaccess with content php_flag engine off
1 




## Font Generation

Note: The following applies when you have a standard vanilla TUG/CTAN TeXLive installation.

* Depending on the latex installation and on the fonts used in the documents, TeX may find that some fonts are missing.
* Information on missing fonts will be found in the extensions/Parsifal/endpoints directory in file missfont.log
* The shell commands in the file missfont.log should be executed to make the respective fontSize
* The fonts then will be available in the home directory of the user making the fonts and there in directory .texlive2021 or similar.





