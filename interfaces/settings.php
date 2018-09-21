<?php
global $brandLibrary;
// Vars required by nu_loader page
$json_config = $brandLibrary['config'];
$json_categories = $brandLibrary['categories'];
$json_modules = $brandLibrary['modules'];


// this is the include file to hold the HTML for the settings view
$libmod_output = "";
$libmod_output .= "<td colspan=\"2\"><h2>JSON Module Library</h2></td></tr>";
foreach( $json_categories as $json_category_slug => $json_category ){
    
    $libmod_output .= "
        <tr class=\"form-table-libmod-category js__libmod-category \">
            <th>${json_category['nicename']}</th>
            <td>${json_category['description']}<table class\"js__libmod-category-modules\">
    ";

    foreach ($json_modules as $json_module) {
        $option_name = $json_module['slug'];
        if( in_array($json_category_slug, $json_module['incategories']) ) {
            $checkedOrNot = esc_attr( get_option ( $option_name ) ) == 'on' ? 'checked="checked"' : '';
            $libmod_output .= "<tr><th><input ${checkedOrNot} class=\"libconfig-main-category-module-checkbox js__libmod-category-module-checkbox\" type=\"checkbox\" name=\"$option_name\" />${json_module['nicename']}<p class=\"libmod-category-module-version\">Version ". $json_module['version'] ."</p></th><td>${json_module['description']}</td></tr>";
        }
    }
    $libmod_output .= "</table></td></tr>";
}

// Exact Current Time (actually needs the timezone set properly still)
$timestamp = date("M j, Y, g:i:s", time());
// Add a hidden input (already whitelisted) that stores the time 'save changes' submitted all the options to the DB
$libmod_output .= "<tr><td><input type=\"hidden\" name=\"last_updated\" value=\"$timestamp\" /></td></tr>";

// 
?>

<div class="wrap">
    <?php settings_errors(); ?>
    <h1>NU Loader Settings</h1><br>
    <h3>Check off what you'd like to load into your site below.</h3>
        <form action="options.php" method="post">
        <?php
        settings_fields( 'nu-loader-settings' );
        do_settings_sections( 'nuloader_options_page' );
        ?>
        <table class="form-table">
            <tr valign="top">
                <th scope="row">Assets Needed</th>
                <td valign="top">
                    <label><input type="checkbox" name="global_material_icons" <?php echo esc_attr( get_option('global_material_icons') ) == 'on' ? 'checked="checked"' : ''; ?> />Global Google Material Icons?</label>
                    <br/>
                    <p class="description" id="tagline-description">The <strong>Global Northeastern Header</strong> requires Google Material Icons.  If your theme is not loading them please check the box above.</p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">Loader Options</th>
                <td valign="top">
                    <label><input type="checkbox" name="global_header" <?php echo esc_attr( get_option('global_header') ) == 'on' ? 'checked="checked"' : ''; ?> />Global Northeastern Header?</label>
                    <br/>
                    <label><input type="checkbox" name="global_footer" <?php echo esc_attr( get_option('global_footer') ) == 'on' ? 'checked="checked"' : ''; ?> />Global Northeastern Footer?</label>
                </td>
            </tr>

            <?php 
            /**
             *  JSON Module Library, very cleanly.
             */
             ?>
            <tr class="form-table-libmod"><?php echo $libmod_output; ?></tr>
            <tr>
                <td>
                    <?php submit_button(); ?>
                </td>
            </tr>
        </table>
        <h2>Need Help?</h2>
        <div id="nu_settings-help">If you need help or something isn't working please <a href="mailto:nudev@northeastern.edu?subject=NU Loader Plugin Help">contact us</a>.</div>
    </form>
</div>
