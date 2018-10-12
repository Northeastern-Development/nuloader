<?php
    /**
     * Ensure site has a 'search' page that is hidden from users
     */
    class SearchPage
    {
        function __construct(){
            add_action('admin_init', array($this, 'check_for_search_page'));
            add_action('pre_get_posts', array($this, 'lock_search_page'));
            add_filter( 'page_template', array($this), 'override_default_page_template' );
            // $this->override_page_template();
        }
        public function do_deactivate_module(){
            error_log('do deactivate module hook fired');
        }
        /**
         * check for the search page; creating it if required
         */
        function check_for_search_page(){
            $searchPage = get_page_by_title('Search', ARRAY_A);
            if( !empty( $searchPage ) )
            {
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
        /**
         * create the search page
         */
        function do_insert_search_page(){
            // there is no search page
            $user_id = get_current_user_id();
            // default args for wp_insert_post
            $args = array(
                'post_author' => $user_id,
                'post_title' => 'Search',
                'post_type' => 'page',
                'post_status' => 'publish',
            );
            // return ID on success or 0 on fail
            $success = wp_insert_post($args);
        }
        /**
         * override the page template for the search page
         */
        function override_page_template($page_template){
            if ( is_page( 'search' ) ) {
                $page_template = dirname( __FILE__ ) . '/tpl.SearchPage.php';
            }
            return $page_template;
        }
        /**
         * hides the search page in the admin area
         * users can be whitelisted by ID if required
         *
         * @param object $wp_query
         */
        function lock_search_page($query){
            if( !is_admin() && !is_main_query() ){
                return;
            }
            // allow certain users to see the hidden page
            $master_user_id = 1;
            if( $master_user_id === get_current_user_id() ){
                return;
            }
            global $typenow;
            if( 'page' === $typenow ){
                $query->set('post__not_in', array(get_page_by_title('Search', ARRAY_A)['ID']));
            }
        }
    }
    $searchPageBuilder = new SearchPage();
?>