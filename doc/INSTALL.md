


# Other aspects


## Server Configuration

We assume:
* the web server runs under user `www-data`.
* the user `www-data` has in `/etc/passwd` no home directory (or rather `/nonexistant`) and as a shell the `bash`.
* the directory `/var/www` exists and is write-able for user `www-data` (required since TeX will write into a font-generation directory in `/var/www` 


## SECURITY ASPECTS

1 tmp should contain a file .htaccess with content php_flag engine off
1 


### Data sets ###
We need php data sets for the cleanUp
We install using:
  pecl install ds

You'll need to add extension=ds.so to your primary php.ini file.


We recommend EXT4 as file system (one reason being [file system performance](https://serverfault.com/questions/98235/how-many-files-in-a-directory-is-too-many-downloading-data-from-net)).

