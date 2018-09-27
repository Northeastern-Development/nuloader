<?php

    if( is_admin() ){
        // admin area        
        add_action('current_screen', 'get_current_screen_info');
        function get_current_screen_info(){
            $screenObj = get_current_screen();
            if( $screenObj->base !== 'toplevel_page_nu_loader' ){
                echo "Module One ( is_admin && !== 'toplevel_page_nu_loader' )";
            }
        }
    } else {
        // front end
        echo "Module One ( !is_admin )";
    }


?>