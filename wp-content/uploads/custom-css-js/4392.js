<!-- start Simple Custom CSS and JS -->
<script type="text/javascript">
/* Default comment here */ 

jQuery(document).ready(function( $ ){
    // Your code in here
	$(window).scroll(function(){
		if($("#headersticks").hasClass("she-header")){
			$("#stickylogsrc").show();
			$("#logoimages").hide();
		}
		else{
			$("#stickylogsrc").hide();
			$("#logoimages").show();
		}
	})
});</script>
<!-- end Simple Custom CSS and JS -->
