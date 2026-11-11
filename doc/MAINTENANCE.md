

## What to do when maintaining Parsifal?

* For manual testing, you can use Parsifal/config/setenv.sh

* php/8.2 
  /etc/php/8.2/fpm/pool.d/www.conf

set p max_children  to higher value !!!

TODO: eventually set ;pm.process_idle_timeout  and  set the strategy to ondemand to have that timeout activated !!!! TESt !!!!!


In den php ini file TODO welchen
php_value[auto_prepend_file]=/home/dante/dantescript/activate-python.php
