<?php

  // this is the include file to hold the HTML for the settings view

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
          <label>
            <input type="checkbox" name="global_material_icons" <?php echo esc_attr( get_option('global_material_icons') ) == 'on' ? 'checked="checked"' : ''; ?> />Global Google Material Icons?
          </label><br/>
          <p class="description" id="tagline-description">The <strong>Global Northeastern Header</strong> requires Google Material Icons.  If your theme is not loading them please check the box above.</p>
        </td>
      </tr>
      <tr valign="top">
        <th scope="row">Loader Options</th>
        <td valign="top">
          <label>
            <input type="checkbox" name="global_header" <?php echo esc_attr( get_option('global_header') ) == 'on' ? 'checked="checked"' : ''; ?> />Global Northeastern Header?
          </label><br/>
          <label>
            <input type="checkbox" name="global_footer" <?php echo esc_attr( get_option('global_footer') ) == 'on' ? 'checked="checked"' : ''; ?> />Global Northeastern Footer?
          </label>
        </td>
      </tr>
      <tr>
        <td><?php submit_button(); ?></td>
      </tr>
    </table>
    <h2>Need Help?</h2>
    <div id="nu_settings-help">
      If you need help or something isn't working please <a href="mailto:nudev@northeastern.edu?subject=NU Loader Plugin Help">contact us</a>.
    </div>
  </form>
</div>
