<?php
/**
 * Plugin Name: NU Loader
 * Plugin URI: http://brand.northeastern.edu/wp/plugins/nuloader
 * Description: 
 *              This plugin adds global university system functionality to your site, including: styles, super nav, utility nav, and footer.
 *              If needed, this plugin will automatically add custom hooks to various locations within your sites theme files. 
 *              This is to allow custom content to be delivered and shown according to university brand guidelines.
 * Version: 1.0.0
 * Author: Northeastern University System
 * Author URI: http://www.northeastern.edu/externalaffairs
 * License: GPL2
 */
class NUModuleLoader
{
    var $resourcesObject
        , $resourcesUrl
		, $brandLibrary
        , $activeComponentSource
    ;


	function __construct()
	{
        /**
         * try to wp_remote_get the global library file
         * if received, set
         * if error, notify...
         */
        $remote_global_components_library = 'https://brand.northeastern.edu/global/components/config/library.json';
        if( !is_wp_error(wp_remote_get($remote_global_components_library)) ){
            // Setup Vars for Global Header / Footer
            $this->resourcesObject = json_decode(wp_remote_get($remote_global_components_library)['body']);
            $this->resourcesUrl = array($this->resourcesObject->config->sourceurl);
        }
        /**
         * try wp_remote_get on the remote json module file,
         * if recieved, set and globalize
         * if wp_remote_get returns a wp_error, display this to the user
         */
        $remote_module_library_file = 'http://sandbox.foo/manageconfig.json';
        if( !is_wp_error(wp_remote_get($remote_module_library_file)) ){
            $this->brandLibrary = json_decode(wp_remote_get($remote_module_library_file)['body'], true);
            global $brandLibrary;
            $brandLibrary = $this->brandLibrary;
        }
        
        // Construct for the Admin Area
        if ( is_admin() )
        {
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
        else if (!is_admin())
        {
			$this->frontend();
        }
         /**
         * handle any installed modules
         */
        $this->handle_exec_installed_modules();
    }

    /**
     * Handle Conditionally Loading NU Modules
     */
    private function handle_exec_installed_modules()
    {
        /**
         * include_once each module in the modules dir
         * expects a .php file in each dir w/ the same name as the folder containing it
         */
        function do_exec_nu_modules()
        {
            $installed_modules = [];
            $modulesContainer = realpath( __DIR__ . '/components/modules/' );
            if( $handle = opendir($modulesContainer) ){
                while( false !== ($entry = readdir($handle)) ){
                    if( $entry != '.' && $entry != ".." && $entry != '.DS_Store' ){
                        $installed_modules[] = $entry;
                    }
                }
                closedir($handle);
            }
            // include_once each modules base.php
            foreach( $installed_modules as $installed_module ){
                include_once($modulesContainer . "/" . $installed_module . "/" . $installed_module . ".php");
            }
        }
        /**
         * check the current page against a blacklist
         * prevent execution of the nu_modules if they meet certain criterea
         */
        function verify_module_execution()
        {
            global $pagenow;
            $exec_blacklist = [
                // 'nu_loader'
            ];
            // conditionally execute in the admin area
            if( is_admin() ){
                if ( in_array($pagenow, $exec_blacklist) || in_array($_GET['page'], $exec_blacklist) ) {
                    return;
                } else {
                    do_exec_nu_modules();
                }
            }
            // always exec on front end
            else {
                do_exec_nu_modules();
            }
        }
        verify_module_execution();
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
        // Add the NULoader Option Page to the Sidebar
        add_action('admin_menu', 'nuloader_add_admin_menu');
        function nuloader_add_admin_menu()
        {
            add_menu_page('NU Loader Settings', 'NU Loader', 'manage_options', 'nu_loader', 'settings_page', plugin_dir_url(__FILE__) . '_ui/n.png');
        }

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
        { 
            global $brandLibrary;
            if( !empty($brandLibrary) )
            {
                // Register a Setting for each Module
                foreach ($brandLibrary['modules'] as $module) {
                    $option_name = $module['slug'];
                    register_setting('nu-loader-settings', $option_name);
                }
                // -- Hidden Setting -- Register "Timestamp" on Save Changes ( with callback to handle logic on saving changes )
                register_setting('nu-loader-settings', 'last_updated', 'on_nuloader_save_changes');
            }


            // Register Settings for Global Header / Footer and Material Icons
            register_setting('nu-loader-settings', 'global_material_icons');
            register_setting('nu-loader-settings', 'global_header');
            register_setting('nu-loader-settings', 'global_footer');
        }

        /**
         * executes when the nu_loader save changes button is clicked
         * hooked to the hidden timestamp field (module loader)
         * $brandLibrary is gauranteed to exist if this setting exists
         */
        function on_nuloader_save_changes(){
            // will always exist
            global $brandLibrary;
            // directory containing installed modules
            $modules_dir = __DIR__ . "/components/modules/";
            // Download a File with cURL
            function curl_get_contents($url)
            {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_HEADER, 0);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
                $data = curl_exec($ch);
                if( curl_error($ch) ){
                    $error_msg = curl_error($ch);
                }
                curl_close($ch);
                if( isset($error_msg) ){
                    error_log(print_r( "cURL errors encountered... \n" . $error_msg, true));
                }
                return $data;
            }
            // Recursively Delete all Files inside Folder, then Delete Folder
            function rrmdir($src)
            {
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
            // Download, hash check, extract and cleanup for modules to be installed
            function download_nu_module_zip($module, $zipFilePath, $moduleDirPath)
            {
                function cleanup_extracted_module_zip($zipFilePath){
                    if( is_writeable($zipFilePath)){
                        unlink($zipFilePath);
                    }
                }
                $remotezip = $module['gitlink'];
                
                // $contents = file_get_contents($remotezip);
                $contents = curl_get_contents($remotezip);
                
                $success = file_put_contents($zipFilePath, $contents);
                
                $hashCheck = verify_file_md5($zipFilePath, $module['hash']);

                if( $hashCheck == 1 ){
                    $zip = new ZipArchive;
                    $result = $zip->open($zipFilePath);
                    if( $result === true ){
                        $zip->extractTo( $moduleDirPath );
                        $zip->close();
                    }
                }
                // delete the zip file after it has been extracted
                cleanup_extracted_module_zip($zipFilePath);
            }
            
            $modules_dir_contents = [];
            if( $handle = opendir(realpath($modules_dir)) ){
                while( false !== ($entry = readdir($handle)) )
                {
                    if( $entry != '.' && $entry != ".." && $entry != '.DS_Store' ){
                        $modules_dir_contents[] = $entry;
                    }
                }
                closedir($handle);
            }

            // array of index => module names
            $enabled_mods = array_keys(array_filter($_POST, function($key){
                // return strpos($key, 'module-') === 0;
                return strpos($key, 'numod-') === 0;
            }, ARRAY_FILTER_USE_KEY));

            
            // multi-demensional array of each modules info from the brandLibrary
            $enabled_mods_objects = array_filter( $brandLibrary['modules'], function($module) use($enabled_mods)
            {
                if( in_array( $module['slug'] , $enabled_mods ) ){
                    return $module;
                }
            });


            // array of modules dirs (version appended)
            $enabled_mods_install_dirs = [];
            foreach ($enabled_mods_objects as $i => $module)
            {
                $enabled_mods_install_dirs[] = $module['slug'] . "_v" . $module['version'];
            }
            

            // array of file/folder paths in the modules directory that should be deleted
            // only directories matching the module-name_version paradigm will exist
            $removablePaths = array_diff($modules_dir_contents, $enabled_mods_install_dirs);
            if( !empty($removablePaths) ){
                foreach( $removablePaths as $removablePath ){
                    // delete any loose files
                    if( is_file(realpath($modules_dir.$removablePath)) )
                    {
                        unlink($modules_dir.$removablePath);
                    }
                    // delete any unchecked or depricated modules (old version)
                    elseif( is_dir($modules_dir.$removablePath) )
                    {
                        // INCLUDE THE MODULE THAT IS ABOUT TO BE DELETED
                        include_once( $modules_dir . '/' . $removablePath . '/' . $removablePath . '.php');
                        // INSTANTIATE IT
                        $handle = new SearchModule();
                        // RUN ITS DEACTIVATION HOOK
                        $handle->do_deactivate_module();
                        // then delete
                        rrmdir($modules_dir.$removablePath);
                    }
                }
            }
            
            // install any missing or updated modules
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

        // Redirect? Thing?
        register_activation_hook(__FILE__, 'nu_loader_plugin_activate');
		function nu_loader_plugin_activate()
		{
            add_option('nu_loader_plugin_do_activation_redirect', true);
        }
        
        // Redirect? Thing?
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

if (!is_admin())
{ // only run this if the user in on the front-end pages
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