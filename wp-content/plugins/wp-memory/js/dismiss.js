/**
 * @ Author: Bill Minozzi
 * */
 jQuery(document).ready(function ($) {
      //console.log('ok!!!!!???');
     jQuery(".wptools_bill_go_pro_dismiss").click(function(event) {
        //  alert('xxxx');
        // console.log('clicou!');
        jQuery("#wp-memory-banner").css("display", "none");
        jQuery.cookie("wpmemory_bill_go_pro_hide", "true", {
            expires: 7,
            secure: 1
        });
     } )

     $(document).on('click', '#wpmemory_an2 .notice-dismiss', function( event ) {
        //alert('1');
        console.log('OK1111!')
        data = {
            action : 'wpmemory_dismissible_notice2',
        };
        $.post(ajaxurl, data, function (response) {
           //  console.log(response, 'DONE!');
        });
    });



});