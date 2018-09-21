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
class NUModuleLoader
{
	var $resourcesUrl
		, $brandLibrary
		, $activeComponentSource
	;
	
	function __construct()
	{
    	// Remote JSON file Location:
		$this->brandLibrary = json_decode(wp_remote_get('http://sandbox.bar/manageconfig.json')['body'], true);
		$this->resourcesUrl = array($this->brandLibrary->config->sourceurl);

		// Construct for the Admin Area
		if (is_admin()) {

			// check the page templates and other resources to make sure that we have everything that we need in place
			if (null !== get_option('global_header') && get_option('global_header') == 'on') {
				$this->checkCustomHook('/header.php', '?><header', '<header', '<?php if(function_exists("NUML_globalheader")){NUML_globalheader();} ?><header');
			}
			if (null !== get_option('global_footer') && get_option('global_footer') == 'on') {
        		// require_once('components/footer.php');
				$this->checkCustomHook('/footer.php', '</footer><?php', '</footer>', '</footer><?php if(function_exists("NUML_globalfooter")){NUML_globalfooter();} ?>');
			}
			// Create / Render Admin Area Customizations and Tools
			$this->admin_tools(); // add the tools to manage settings
		}
		// Construct for the Front End
		else if (!is_admin()) { // this is a front-end request, so build out any front-end components needed
			$this->frontend();
		}
	}



	// check to see if the requested module has already been initialized
	private function checkCustomHook($a = '', $b = '', $c = '', $d = '')
	{
		$p = get_template_directory() . $a;
		$f = fopen($p, "r") or die('Unable to open file!');
		$data = fread($f, filesize($p));
		fclose($f);
		if (!strpos($data, $b)) { // we need to update the template
			$data = str_replace($c, $d, $data);
			$f = fopen($p, "w+") or die('ERROR: Unable to add custom hook');
			fwrite($f, $data);
			fclose($f);
		}
		unset($p, $f, $d, $a, $b, $c, $data);
	}



	// this function gets run when on the admin pages
	private function admin_tools()
	{
		/* 
			Begin NU Loader:
		*/
			// Add the NULoader Option Page
			add_action('admin_menu', 'nuloader_add_admin_menu'); // adds menu item to wp dashboard
			function nuloader_add_admin_menu()
			{
				add_menu_page('NU Loader Settings', 'NU Loader', 'manage_options', 'nu_loader', 'settings_page', plugin_dir_url(__FILE__) . '_ui/n.png');
			}

			// Globalize the brandLibrary
			global $brandLibrary;
			$brandLibrary = $this->brandLibrary;
		
		
			// Enqueue NU Loader Styles and Scripts
			add_action('admin_enqueue_scripts', 'enqueue_jsonlib_scripts');
			function enqueue_jsonlib_scripts()
			{
				wp_enqueue_script('jsonlib_script', plugin_dir_url(__FILE__) . "scripts/json_lib.js");
				wp_enqueue_style('jsonlib_styles', plugin_dir_url(__FILE__) . "scripts/json_lib.css");
			}

			// Register (whitelist) options for NULoader
			add_action('admin_init', 'register_mysettings');
			function register_mysettings()
			{ // whitelist options
				register_setting('nu-loader-settings', 'global_material_icons');
				register_setting('nu-loader-settings', 'global_header');
				register_setting('nu-loader-settings', 'global_footer');
                global $brandLibrary;
                foreach ($brandLibrary['modules'] as $module) {
                    $option_name = $module['slug'];
                    register_setting('nu-loader-settings', $option_name);
                }
				// Timestamp field for recording the last save changes
				register_setting('nu-loader-settings', 'last_updated', 'on_nuloader_save_changes');
            }

            /**
             * On Save Changes Hook:
             *
             */
            function on_nuloader_save_changes(){
                // Library
                global $brandLibrary;
                // Recursively Delete all Files inside Folder, then Delete Folder
                function rrmdir($src) {
                    $dir = opendir($src);
                    while(false !== ( $file = readdir($dir)) ) {
                        if (( $file != '.' ) && ( $file != '..' )) {
                            $full = $src . '/' . $file;
                            if ( is_dir($full) ) {
                                rrmdir($full);
                            }
                            else {
                                unlink($full);
                            }
                        }
                    }
                    closedir($dir);
                    rmdir($src);
                }
                // path to dir containing all installed modules (in subdirs)
                $modules_dir = __DIR__ . "/components/modules/";
                // array of paths to each subdirectory of $modules_dir
                // each should (only) represent an installed module
                $modules_dir_contents = [];
                if( $handle = opendir(realpath($modules_dir)) ){
                    while( false !== ($entry = readdir($handle)) ){
                        if( $entry != '.' && $entry != ".." && $entry != '.DS_Store' ){
                            $modules_dir_contents[] = $entry;
                        }
                    }
                    closedir($handle);
                }
                // array of option_name values for each checked module submitted on save changes
                $enabled_mods = array_keys(array_filter($_POST, function($key) {
                    return strpos($key, 'module-') === 0;
                }, ARRAY_FILTER_USE_KEY));
                // array of module information for each of $enabled_mods
                $enabled_mods_objects = array_filter( $brandLibrary['modules'], function($module) use($enabled_mods){
                    if( in_array( $module['slug'] , $enabled_mods ) ){
                        return $module;
                    }
                });
                // array of formatted strings representing paths modules should be extracted to (or already exist within)
                $enabled_mods_install_dirs = [];
                foreach ($enabled_mods_objects as $i => $enabled_mod_object) {
                    $enabled_mods_install_dirs[] = $enabled_mod_object['slug'] . "_v" . $enabled_mod_object['version'];
                }
                // array of paths that already exist locally from the submitted option_names after formatting w/ version
                $verified_local_mods = array_intersect($modules_dir_contents, $enabled_mods_install_dirs);
                // (may want to check that the dir is not empty as well)
                // array of paths that already exist locally that DO NOT match submitted option_names after formatting w/ version
                $modules_dir_junk = array_diff($modules_dir_contents, $enabled_mods_install_dirs);
                if( !empty($modules_dir_junk) ){
                    foreach( $modules_dir_junk as $i => $junk ){
                        if( is_file(realpath($modules_dir . $junk)) ){
                            unlink($modules_dir.$junk);
                        } elseif( is_dir($modules_dir.$junk) ){
                            rrmdir($modules_dir.$junk);
                        }
                    }
                }

                function cleanup_extracted_module_zip($zipFilePath){
                    if( is_writeable($zipFilePath)){
                        unlink($zipFilePath);
                    }
                }
                function download_nu_module_zip($module, $zipFilePath, $moduleDirPath){
                    $remotezip = $module['gitlink'];
                    $contents = file_get_contents($remotezip);
                    $success = file_put_contents($zipFilePath, $contents);
                    $zip = new ZipArchive;
                    $result = $zip->open($zipFilePath);
                    if( $result === true ){
                        $zip->extractTo( $moduleDirPath );
                        $zip->close();
                    }
                    cleanup_extracted_module_zip($zipFilePath);
                }
                foreach( $enabled_mods_objects as $module ){
                    $moduleDirPath = $modules_dir . $module['slug'] . "_v" . $module['version'];
                    $zipFilePath = realpath($modules_dir)."/".$module['slug'].".zip";
                    if( !is_dir($moduleDirPath) ){
                        download_nu_module_zip($module, $zipFilePath, $moduleDirPath);
                    }
                }
            }

            // called to output the content of the 'nu_loader' page added above
            function settings_page()
            {
                include('interfaces/settings.php'); // call in the settings interface
            }
        /* 
            End NULoader
        */
        register_activation_hook(__FILE__, 'nu_loader_plugin_activate');
		function nu_loader_plugin_activate()
		{
			add_option('nu_loader_plugin_do_activation_redirect', true);
		}
		add_action('admin_init', 'nu_loader_plugin_redirect');
		function nu_loader_plugin_redirect()
		{
			if (get_option('nu_loader_plugin_do_activation_redirect', false)) {
				delete_option('nu_loader_plugin_do_activation_redirect');
				if (!isset($_GET['activate-multi'])) {
					wp_redirect("admin.php?page=nu_loader");
				}
			}
		}
	}


	// 
	private function frontend()
	{
    	// do we want to add in material icons?
		if (null !== get_option('global_material_icons') && get_option('global_material_icons') == 'on') {
			add_action('wp_head', array($this, 'nu_materialicons'));//CSS
		}

		// add in the footer CSS if they have activated that module
		if (null !== get_option('global_footer') && get_option('global_footer') == 'on') {
			add_action('wp_head', array($this, 'nu_footerstyles'));
		}

    	// add in the header CSS and JS if they activated that module
		if (null !== get_option('global_header') && get_option('global_header') == 'on') {
			add_action('wp_head', array($this, 'nu_headerstyles'));//CSS
			add_action('wp_footer', array($this, 'nu_scripts'));//JS
		}
	}


  // build out the footer to be shown on the site
	public function build_footer()
	{
		if (null !== get_option('global_footer') && get_option('global_footer') == 'on') {
			echo '<div id="nu__global-footer">' . $this->getRemoteContent('/resources/includes/?r=footer&cache=no') . '</div>';
		}
	}

	// add the footer styles to the header
	function nu_footerstyles()
	{
		echo '<link  rel="stylesheet" id="global-footer-style-css"  href="' . $this->resourcesUrl[0] . '/nuglobalutils/common/css/footer.css" /> ';
	}

	// build out the header to be shown on the site
	public function build_header()
	{
		if (null !== get_option('global_header') && get_option('global_header') == 'on') {

			$return = '<div id="nu__globalheader">';

			// are there any alerts that we need to show?
			// $return .= $this->getRemoteContent('/resources/components/?return=alerts&cache=no');
			// $return .= wp_remote_get('http://newnu.local/resources/components/?return=alerts&cache=no')['body'];

			// grab the content for the main menu
			$return .= $this->getRemoteContent('/resources/components/?return=main-menu&cache=no');

			$return .= '</div>';

			echo $return;

			unset($return);
		}
	}





	// this function performs the actual remote content request and returns only the body value
	private function getRemoteContent($a = '') : string
	{
		$return = wp_remote_get( $this->resourcesUrl[0] . $a );
		if ( !is_wp_error( $return['body']) ) {
			return $return['body'];
		}
		else {
			return 'ERROR: the remote content could not be returned.';
		}
	}

	// add in the JS for the global header
	function nu_scripts()
	{
		echo '<script src="' . $this->resourcesUrl[0] . '/nuglobalutils/common/js/navigation.js"></script>';
		// echo '<script src="http://sandbox.local/globalheaderfooter/server/js/navigation.js"></script>';
	}

	// add in the CSS for the header
	function nu_headerstyles()
	{
		echo '<link  rel="stylesheet" id="global-header-style-css"  href="' . $this->resourcesUrl[0] . '/nuglobalutils/common/css/utilitynav.css"  />';
    	// echo '<link  rel="stylesheet" id="global-header-style-css"  href="http://sandbox.local/globalheaderfooter/server/css/utilitynav.css" />';
	}

	// add in the material icons CSS
	function nu_materialicons()
	{
		echo '<link  rel="stylesheet" id="global-font-css"  href="' . $this->resourcesUrl[0] . '/nuglobalutils/common/css/material-icons.css"/>';
	}

}

// initialize new object
$NUML = new NUModuleLoader();

if (!is_admin()) { // only run this if the user in on the front-end pages
	function NUML_globalfooter()
	{
		global $NUML;
		$NUML->build_footer();
	}

	function NUML_globalheader()
	{
		global $NUML;
		$NUML->build_header();
	}
}