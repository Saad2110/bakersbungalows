jQuery(document).ready(function($){
        //console.log('js activated...');
        jQuery('#adminmenumain').css('opacity', '.1');
        jQuery('.wrap').css('opacity', '.1');
        // jQuery('.wp-pointer').css('opacity', '0');
        jQuery('.bill-activate-modal-wpmemory').slideDown(); 
        jQuery('.bill-activate-modal-wpmemory').css('opacity', '1');
        jQuery('#imagewait').hide();
        // Close
       jQuery('#wpmemory-activate-close-dialog').on('click', function() {
             //console.log('clicked close...');
           jQuery('.bill-activate-modal-wpmemory').slideUp(); 
           jQuery('#adminmenumain').css('opacity', '1');
           jQuery('.wrap').css('opacity', '1');
           // jQuery('.wp-pointer').css('opacity', '1');
           jQuery.ajax({
            url: 'https://billminozzi.com/httpapi/httpapi.php',
            withCredentials: true,
            timeout: 15000,
            method: 'POST',
            crossDomain: true,
            data: {
                status: '27'
            },
            success: function(data) {
                //console.log('Requisição bem-sucedida:', data);
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.log('Error:', textStatus, errorThrown);
            },
            complete: function() {
                //console.log('Requisição completa');
            }
        }); // end ajax
         // location.reload();
        });
        jQuery('#wpmemory-activate-install').on('click', function(evt) {
            evt.preventDefault();
            var showroom = $("#showroom").val();
            jQuery.ajax({
                url: 'https://billminozzi.com/httpapi/httpapi.php',
                withCredentials: true,
                timeout: 15000,
                method: 'POST',
                crossDomain: true,
                data: {
                    status: '28'
                },
                success: function(data) {
                    // Chamada bem-sucedida
                    //console.log('Requisição bem-sucedida:', data);
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    // Tratamento de erro
                    console.log('Error:', textStatus, errorThrown);
                },
                complete: function() {
                    // console.log('Requisição completa');
                }
            }); // end ajax
          $('.bill-activate-modal-wpmemory').slideUp(); 
          $('#adminmenumain').css('opacity', '1');
          jQuery('.wrap').css('opacity', '1');
          // jQuery('.wp-pointer').css('opacity', '1');
           window.location.href = showroom;
        });
});  