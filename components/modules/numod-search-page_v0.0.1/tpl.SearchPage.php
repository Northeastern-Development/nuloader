<?php
/*
Template Name: Search Template
*/
    if(!isset($_GET['query']) || $_GET['query'] == ''){
        header('location:/search/?query=northeastern university');
        exit();
    }
    get_header();
 ?>
    <main>
        <section>
            <script src="https://search.northeastern.edu/nuglobalutils/requests/js/globalsearch.js"></script>
        </section>
    </main>
<?php 
    get_footer();
 ?>