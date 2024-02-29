/*
 * @ Author: Bill Minozzi
 * @ Copyright: 2021 www.BillMinozzi.com
 * @ Modified time: 2021-29-11 09:17:42
 * */
jQuery(document).ready(function ($) {
     //console.log('vendor-wpm');
     $("#wpmemory-scan-ok").click(); 
    $("#TB_title").hide();
    if (!$("#TB_window").is(':visible')) {
        $("#wpmemory-scan-ok").click();
        // console.log('auto click');
    }
        $("*").click(function (ev) {
            //  alert('2');
          //  console.log('click');
            if(ev.target.id =="bill-vendor-button-again-wpm" ) {
                //console.log(ev.target.id);
                $("#bill-banner-wpm").get(0).play();
            }
            if(ev.target.id =="bill-vendor-button-ok-wpm" ) {
                //console.log(ev.target.id);
                window.location.replace("http://wpmemory.com/premium/");
            }
            if(ev.target.id =="bill-vendor-button-dismiss-wpm" ) {
                        //ev.preventDefault() 
                        //console.log('clicked !!!---------!!!'); 
                        jQuery.ajax({
                            method: 'post',
                            url: ajaxurl,
                            data: {
                                action: "wpmemory_bill_go_pro_hide"
                            },
                            success: function (data) {
                                console.log('OK-dismissed!!!');
                                // return data;
                            },
                            error: function (jqXHR, textStatus, errorThrown) {
                                alert('error' + errorThrown + ' ' + textStatus);
                            }
                        });
                        //console.log("fechar");
                        self.parent.tb_remove();
                        // $("#TB_window").hide();
                        // TB_closeWindowButton
                        $('#TB_window').fadeOut();
                        $("#TB_closeWindowButton").click();
            }
        });
    if ($('#bill-banner-wpm').length) {
       //  $("#bill-banner").get(0).play();
       $("#bill-banner-wpm").get(0).play().catch(function() {
        // console.log("Fail to Play.");
        self.parent.tb_remove();
        $('#TB_window').fadeOut();
        $("#TB_closeWindowButton").click();
    });
    }
    $("#TB_window").height(260);
    $("#TB_window").width(550);
    $("#TB_window").addClass("bill_TB_window");
});