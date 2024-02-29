<!-- start Simple Custom CSS and JS -->
<script type="text/javascript">
var testRole = jQuery("div.woocommerce-notices-wrapper > ul > li:nth-child(1)").text().trim();
if(testRole=="The order ID from the queried transaction does not match the expected order ID."){
   setTimeout(function(){ window.location.href='/order-confirmed/'; }, 0);  
   }
else{
   
}
// alert(jQuery("div.woocommerce-notices-wrapper > ul > li:nth-child(1)").text().trim());
// jQuery('#place_order').click(function() {
// setTimeout(function(){ window.location.href='/order-confirmed/'; }, 2000);   
//     return false;
// });
</script>
<!-- end Simple Custom CSS and JS -->
