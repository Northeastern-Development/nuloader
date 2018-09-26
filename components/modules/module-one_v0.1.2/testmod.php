<?php 



    // A test module that should produce a visible effect when loaded by NULoader,
    // May set certain variables that are expected by the plugin or within a theme to alter functionality,
    // May include its own methods that do things invisibly or exclusive to any other plugin or theme settings.

    // Who knows! Lets try something fun for now.

    $possibleLocations = array(
        "Disguised as Carmen Sandiago!",
        "In a Pokeball!",
        "Flying First Class!"
    );

    function wheresWaldo($possibleLocations){
        if( !empty($possibleLocations) ) {
            return array_rand($possibleLocations);
        }
    }



?>