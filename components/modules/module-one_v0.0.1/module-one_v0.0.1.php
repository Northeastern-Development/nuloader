<?php
/**
 *  Ensure Search Page Exists
 */
    class SearchModule
    {
        // set required vars
        var $someVars;

        function __construct(){
            add_action('admin_init', array($this, 'check_for_search_page'));
        }

        function check_for_search_page(){
            
            $searchPage = get_page_by_title('Search', ARRAY_A);

            if( !empty( $searchPage ) ){

                if( $searchPage['post_status'] === 'trash' ){
                    error_log(print_r( "Search Page was found with status: \n" . $searchPage['post_status'], true));
                    // post in trash
                }
                
                else if( $searchPage['post_status'] === 'publish' ){
                    error_log(print_r( "Search Page was found with status: \n" . $searchPage['post_status'], true));
                    // post is published
                    // check its page template,
                    
                }

                else if( $searchPage['post_status'] != 'publish' ){
                    // post is either pending, draft, auto-draft, future, private, or 'inherit'
                    error_log(print_r( "Search Page was found with status: \n" . $searchPage['post_status'], true));
                }
            }

            // page will not be created if in the trash
            else if( empty($searchPage) ){
                $this->do_insert_search_page();
            }

        }

        function do_insert_search_page(){
         
            // there is no search page
            $user_id = get_current_user_id();
         
            // default args for wp_insert_post
            $args = array(
                'post_author' => $user_id,
                'post_title' => 'Search',
                'post_type' => 'page',
                'post_name' => '',      // will default to serialized title
                'post_status' => 'publish',
                'post_content' => '',
                'post_content_filtered' => '',
                'post_excerpt' => '',
                'comment_status' => '',
                'ping_status' => '',
                'post_password' => '',
                'to_ping' =>  '',
                'pinged' => '',
                'post_parent' => 0,
                'menu_order' => 0,
                'guid' => '',
                'import_id' => 0,
                'context' => '',
            );
         
            // return ID on success or 0 on fail
            $success = wp_insert_post($args);

            if( $success ){
                $this->do_configure_search_page($success);
            }
        }


        function do_configure_search_page($page_id){

            // assign the page template!

        }




    }

    $searchPageBuilder = new SearchModule();

?>