jQuery(document).ready(function() {
	setInterval(
		function callAjax() {
			jQuery.ajax({
			    type: "POST",
			    url: ajaxurl,
			    data: { action: 'my_action' , param: 'st1' }
			  }).done(function( msg ) {
			         alert( "Data Saved: " + msg.response );
			 });	
		}, 3000);	
})