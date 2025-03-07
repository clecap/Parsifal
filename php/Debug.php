<?php

// this file realizes the Parsifal Special Page Resetting the entire system

require_once (  dirname(__FILE__) . "/../config/config.php");    // include path configuration

class ParsifalDebug extends FormSpecialPage {

  public function __construct () {;
    parent::__construct( 'ParsifalDebug', 'resetParsifal' ); }
  
  public function getGroupName() {return 'dante';}
  
  public function onSubmit( array $data )  {
    global $IP;
    $VERBOSE = false;  // CAVE: if we debug this and set this to true we MUST comment away the deletion of LOGFILE below or we will not see what we log !!

    if (! $this->getUser()->isAllowed ("resetParsifal") ) {return false;}                                       // check for permission "resetParsifal"  

    // NOTE: need to reset apcu cache and opcache to have this effective as soon as possible in all transitions
    if ($data["radio"] == 0) { copy ("$IP/DanteSettings-production.php",          "$IP/DanteSettings-used.php");   apcu_clear_cache(); opcache_reset(); }
    if ($data["radio"] == 1) { copy ("$IP/DanteSettings-development.php",         "$IP/DanteSettings-used.php");   apcu_clear_cache(); opcache_reset(); }
    if ($data["radio"] == 2) { copy ("$IP/DanteSettings-development-deprec.php",  "$IP/DanteSettings-used.php");   apcu_clear_cache(); opcache_reset(); }

    return "Please wait, reloading...<script>window.location.reload();</script>";

  }

  public function getFormFields()  {
    global $wgDanteOperatingMode;
    $output = $this->getOutput();
   	$output->addHTML( "<h3>Current operating mode is: $wgDanteOperatingMode.</h3><br>This form allows to select the operative mode<br>" );
   	$output->addHTML( '<b>Running in development or development & deprecation mode contains security risks and is discouraged!</b><br>' );
    $output->addHTML( '<b>Unless you know exactly what you are doing, please close this browser window immediately</b>' );

     $formDescriptor = [
      'radio' => [
          'type' => 'radio',
          'label' => 'Operative mode',
          'options' => [
              'Production' => 0,
              'Development' => 1,
              'Development & Deprecation' => 2
          ],
      // The options selected by default (identified by value)
          'default' => 1,
      ]
  ];
  
    return $formDescriptor;




  }

  
}
