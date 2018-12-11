<?php
  class NUModuleFooter extends NUModuleLoader{

    function __construct(){

      $this->checkCustomHook('/footer.php','</footer><?php','</footer>','</footer><?php if(function_exists("NUML_globalfooter")){NUML_globalfooter();} ?>');

    }



  }
?>
