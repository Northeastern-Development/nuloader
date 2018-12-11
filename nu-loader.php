<?php
/**
* Plugin Name: NU Loader
* Plugin URI: http://brand.northeastern.edu/wp/plugins/nuloader
* Description: This plugin adds global university system functionality to your site, including: styles, super nav, utility nav, and footer.  If needed, this plugin will automatically add custom hooks to various locations within your sites theme files.  This is to allow custom content to be delivered and shown according to university brand guidelines.
* Version: 1.0.0
* Author: Northeastern University System
* Author URI: http://www.northeastern.edu/externalaffairs
* License: GPL2
*/





class NUModuleLoader{

  var $resourcesUrl
      ,$brandLibrary
      ,$activeComponentSource
      ,$debugMode
      ,$debugUrl
      ,$errorOn;

  // initialize
  function __construct(){

    $this->errorOn = false;
    $this->debugMode = false;
    $this->debugUrl = 'http://sandbox.local/globalheaderfooter';

    // let's read in the remote config JSON file
    $configJson = wp_remote_get('https://brand.northeastern.edu/global/components/config/library.json');

    if(!is_wp_error($configJson)){  // we do NOT have a wordpress error as the returned value, so let's do this
      $this->brandLibrary = json_decode($configJson['body']);

      $this->resourcesUrl = array($this->brandLibrary->config->sourceurl);

      if(is_admin()){ // this is a back-end page request only (so it can be a little slower)
        // check the page templates and other resources to make sure that we have everything that we need in place
        if(null !== get_option('global_header') && get_option('global_header') != 'on'){  // only runs if not already active
          $this->checkCustomHook('/header.php','?><header','<header','<?php if(function_exists("NUML_globalheader")){NUML_globalheader();} ?><header');
        }
        if(null !== get_option('global_footer') && get_option('global_footer') != 'on'){  // only runs if not already active

          $this->checkCustomHook('/footer.php','</footer><?php','</footer>','</footer><?php if(function_exists("NUML_globalfooter")){NUML_globalfooter();} ?>');
        }

        $this->admin_tools(); // add the tools to manage settings

      }else if(!is_admin()){ // this is a front-end request, so build out any front-end components needed
        $this->frontend();
      }
    }else{
      $this->errorOn = true;
      parent::__construct();  // call the constructor again until we get everything set up and working as it should be
    }
  }





  // check to see if the requested module has already been initialized
  private function checkCustomHook($a='',$b='',$c='',$d=''){

    $p = get_template_directory().$a;
    $f = fopen($p, "r") or die('Unable to open file!');
    $data = fread($f,filesize($p));
    fclose($f);

    if(!strpos($data,$b)){ // we need to update the template
      $data = str_replace($c,$d,$data);
      $f = fopen($p, "w+") or die('ERROR: Unable to add custom hook');
      fwrite($f,$data);
      fclose($f);
    }

    unset($p,$f,$d,$a,$b,$c,$data);
  }





  // this function gets run when on the admin pages
  private function admin_tools(){

    register_activation_hook(__FILE__, 'nu_loader_plugin_activate');

    add_action( 'admin_menu','nuloader_add_admin_menu'); // adds menu item to wp dashboard
    add_action( 'admin_init','register_mysettings');
    add_action('admin_init', 'nu_loader_plugin_redirect');

    function nuloader_add_admin_menu(){
      add_menu_page( 'NU Loader Settings', 'NU Loader', 'manage_options', 'nu_loader', 'settings_page', plugin_dir_url( __FILE__ ) . '_ui/n.png' );
    }

    function register_mysettings(){ // whitelist options
      register_setting( 'nu-loader-settings', 'global_material_icons' );
      register_setting( 'nu-loader-settings', 'global_header' );
      register_setting( 'nu-loader-settings', 'global_footer' );
    }

    function settings_page(){
      include('interfaces/settings.php'); // call in the settings interface
    }

    function nu_loader_plugin_activate(){
      add_option('nu_loader_plugin_do_activation_redirect', true);
    }

    function nu_loader_plugin_redirect(){
      if(get_option('nu_loader_plugin_do_activation_redirect', false)){
        delete_option('nu_loader_plugin_do_activation_redirect');
        if(!isset($_GET['activate-multi'])){
          wp_redirect("admin.php?page=nu_loader");
        }
      }
    }

  }


  private function frontend(){

    // do we want to add in material icons?
    if(null !== get_option('global_material_icons') && get_option('global_material_icons') == 'on'){
      add_action('wp_head',array($this,'nu_materialicons'));//CSS
    }

    // add in the footer CSS if they have activated that module
    if(null !== get_option('global_footer') && get_option('global_footer') == 'on'){
      add_action('wp_head',array($this,'nu_footerstyles'));
    }

    // add in the header CSS and JS if they activated that module
    if(null !== get_option('global_header') && get_option('global_header') == 'on'){
      add_action('wp_head',array($this,'nu_headerstyles'));//CSS
      add_action('wp_footer',array($this,'nu_scripts'));//JS
    }

  }


  // build out the footer to be shown on the site
  public function build_footer(){

    if(null !== get_option('global_footer') && get_option('global_footer') == 'on'){
      echo '<div id="nu__global-footer">'.$this->getRemoteContent('/resources/includes/?r=footer&cache=no').'</div>';
    }

  }


  // add the footer styles to the header
  function nu_footerstyles(){
    if(!$this->debugMode){  // we are not in debug mode
      echo '<link  rel="stylesheet" id="global-footer-style-css"  href="'.$this->resourcesUrl[0].'/nuglobalutils/common/css/footer.css" /> ';
    }else{  // we are in debug mode
      echo '<link  rel="stylesheet" id="global-footer-style-css"  href="'.$this->debugUrl.'/server/css/footer.css" /> ';
    }
  }





  // build out the header to be shown on the site
  public function build_header(){

    if(null !== get_option('global_header') && get_option('global_header') == 'on'){

      $return = '<div id="nu__globalheader">';

      // are there any alerts that we need to show?
      // if(!$this->debugMode){
      //   $return .= $this->getRemoteContent('/resources/components/?return=alerts&cache=no');
      // }else{
      //   $return .= wp_remote_get('http://newnu.local/resources/components/?return=alerts&cache=no')['body'];
      // }

      // grab the content for the main menu
      $return .= $this->getRemoteContent('/resources/components/?return=main-menu&cache=no');

      $return .= '</div>';

      echo $return;

      unset($return);
    }

  }





  // this function performs the actual remote content request and returns only the body value
  private function getRemoteContent($a=''){
    if(!$this->errorOn){  // make sure that we do not have a random error blocking us for any reason
      if(!$this->debugMode){  // we are not in debug mode
        $return = wp_remote_get($this->resourcesUrl[0].$a);
      }else{  // we are in debug mode
        $return = wp_remote_get('http://newnu.local/'.$a);
      }
      if(!is_wp_error($return)){
        return $return['body'];
      }else{
        return 'ERROR: the remote content could not be returned.';
      }
    }
  }





  // add in the JS for the global header
  function nu_scripts(){
    if(!$this->debugMode){  // we are not in debug mode
      echo '<script src="'.$this->resourcesUrl[0].'/nuglobalutils/common/js/navigation.js"></script>';
    }else{  // we are in debug mode
      echo '<script src="'.$this->debugUrl.'/server/js/navigation.js"></script>';
    }
  }


  // add in the CSS for the header
  function nu_headerstyles(){
    if(!$this->debugMode){  // we are not in debug mode
      echo '<link  rel="stylesheet" id="global-header-style-css"  href="'.$this->resourcesUrl[0].'/nuglobalutils/common/css/utilitynav.css"  />';
    }else{  // we are in debug mode
      echo '<link  rel="stylesheet" id="global-header-style-css"  href="'.$this->debugUrl.'/server/css/utilitynav.css" />';
    }
  }


  // add in the material icons CSS
  function nu_materialicons(){
    if(!$debugMode){  // we are not in debug mode
      echo '<link  rel="stylesheet" id="global-font-css"  href="'.$this->resourcesUrl[0].'/nuglobalutils/common/css/material-icons.css"/>';
    }else{  // we are in debug mode
      echo '<link  rel="stylesheet" id="global-header-style-css"  href="'.$this->debugUrl.'/server/css/material-icons.css" />';
    }
  }

}





// initialize new object
$NUML = new NUModuleLoader();

if(!is_admin()){ // only run this if the user in on the front-end pages
  function NUML_globalfooter(){
    global $NUML;
    $NUML->build_footer();
  }

  function NUML_globalheader(){
    global $NUML;
    $NUML->build_header();
  }
}
