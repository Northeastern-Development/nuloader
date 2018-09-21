var Kit = {};

jQuery( document ).ready(function($) {
    /**
     *  Manage checkboxes for the JSON Library Config Feature
     */
    Kit.JsonLib = {
        // Get checkboxes by their shared class
        checkboxes : $('.js__libmod-category-module-checkbox'),
        // event-handling the 'change' event for the checkboxes ()
        _checkHandler : function(e){
            // find all other checkboxes with said value
            Kit.JsonLib.checkboxes.each(function(){
                // check or uncheck all w/ same name attribute
                if( e.target.name == this.name){
                    if( $(e.target).prop("checked") ){
                        $(this).prop('checked', true);
                    } else {
                        $(this).prop('checked', false);
                    }
                }
            });
        },
        _init : function(){
            // when ever a checkbox is checked, exec the handler
            $(Kit.JsonLib.checkboxes).on('change', Kit.JsonLib._checkHandler);
        }
    }
    Kit.JsonLib._init();


    Kit.ModuleToggler = {

        anyCategory : $('.js__libmod-category'),
        
        _toggleModuleVisibility : function(e){
            Kit.ModuleToggler.anyCategory.removeClass('js__libmod-category--opened');
            $(this).addClass('js__libmod-category--opened');
        },
        _init : function(){
            $(Kit.ModuleToggler.anyCategory).on('click', Kit.ModuleToggler._toggleModuleVisibility);
        }
    }
    Kit.ModuleToggler._init();
    
    
});
