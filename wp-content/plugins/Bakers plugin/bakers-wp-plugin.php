<?php
/**
* Plugin Name: Bakersplugin
* Plugin URI: http://URI_Of_Page_Describing_Plugin_and_Updates
* Description: A brief description of the Plugin.
* Version: The Plugin's Version Number, e.g.: 1.0
* Author: Name Of The Plugin Author
* Author URI: http://URI_Of_The_Plugin_Author
* License: A "Slug" license name e.g. GPL2
*/

add_action('wp_footer', 'timer');
add_action('wp_footer', 'popupbutton');
function timer() {

    ?>
<script type="text/javascript">
    jQuery(document).ready(function(){

        jQuery("<div id='disclaimer-hide' class='vbo-booking-details-head vbo-booking-details-head-pending disclaimer'><h4>Thank you for your reservation. Please complete your booking by clicking the “Pay Now Button” below. If payment is not completed in the next 30 minutes the reservation will no longer be held in our system.</h4></div><div class='countdown'></div><div id='timer'></div><div id='disclaimer' class='disclaimer' style='display:none;'><h4>We’re sorry but this reservation no longer exists, please<a href='/'> click here</a> to return to the homepage and resubmit your booking details.</h4></div>").insertAfter(".vbo-booking-details-head-pending");
        
    });
</script>
    <script>
// properties
var count = 0;
var counter = null;
jQuery(document).ready(function(){

    jQuery(".booknow").click(function(){
        sessionStorage.setItem("count", 1800);
    });
// get count from localStorage, or set to initial value of 1000
var count2=sessionStorage.getItem("count");
if(count2 > 0){

}else if(count2==-1){
    jQuery(".vbvordpaybutton").hide();
    jQuery(".vbo-booking-details-midcontainer").hide();
    jQuery(".vbo-booking-rooms-wrapper").hide();
    jQuery(".vbo-booking-costs-list").hide();
    jQuery("#disclaimer-hide").hide();
    jQuery(".vbo-booking-details-head-pending").hide();
    jQuery("#stripe-checkout-button").hide();
    jQuery("#timer").hide();
}
else{
    sessionStorage.setItem("count", 1800);
}

count = sessionStorage.getItem("count");
counter = setInterval(timer, 1000); //1000 will  run it every 1 second

});


function timer() {
    var test=sessionStorage.getItem("count");
    count =  sessionStorage.setItem("count", test - 1);
    var test2=sessionStorage.getItem("count");
// count = setLocalStorage('count', count - 1);
if (test2 == -1) {
    clearInterval(counter);
    return;
}

var seconds = test2 % 60;
var minutes = Math.floor(test2 / 60);
var hours = Math.floor(test2 / 60);
minutes %= 60;
hours %= 60;
if (minutes < 1 && seconds< 1) {
    jQuery("#disclaimer").show();
//alert(minutes);
jQuery(".vbvordpaybutton").hide();
jQuery(".vbo-booking-details-midcontainer").hide();
jQuery(".vbo-booking-rooms-wrapper").hide();
jQuery(".vbo-booking-costs-list").hide();
jQuery("#disclaimer-hide").hide();
jQuery(".vbo-booking-details-head-pending").hide();
jQuery("#stripe-checkout-button").hide();
jQuery("#timer").hide();
}
if (jQuery('#timer').length) {
if(seconds< 10){
    document.getElementById("timer").innerHTML = minutes +  ": 0"   + seconds +  " "; 
} else if(minutes < 10){
    document.getElementById("timer").innerHTML ="0"+ minutes +  ": "   + seconds +  " "; 
}
else if(minutes < 10 && seconds< 10){
    document.getElementById("timer").innerHTML ="0"+ minutes +  ": 0"   + seconds +  " "; 
}
else{
    document.getElementById("timer").innerHTML =minutes +  ": "   + seconds +  " "; 
}
}
}
</script>



<style>
.countdown{
    display:none;
    font-size: 25px;
    font-family: ui-monospace;
    font-weight: 600;
}
#timer{
    border:1px solid grey;
    font-size: 40px;
    font-family: ui-monospace;
    font-weight: 600;
}
.disclaimer{
    background: transparent;
    color:black;
}
.wpforms-submit{
    color:white !important;
}
.wpforms-submit-container{
    text-align: center !important;
}
div.wpforms-container-full .wpforms-form input[type=submit], div.wpforms-container-full .wpforms-form button[type=submit], div.wpforms-container-full .wpforms-form .wpforms-page-button {
    background-color: #eee;
    border: 1px solid #ddd;
    color: white;
    font-size: 1em;
    padding: 10px 15px;
}
</style>
<?php

}


function popupbutton() {

    ?>
    <script type="text/javascript">
        jQuery(document).ready(function(){

            jQuery("<div class='popupbutton'><button class='btn vbo-pref-color-btn'>Click Here to be added to the Waiting List</button></div>").insertAfter(".err");

        });
    </script>
    <style>
    .popupbutton {
        text-align: center;
    }
</style>
<?php

}