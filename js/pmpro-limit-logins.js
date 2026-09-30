jQuery(document).ready(function() {
	jQuery.ajax({
		url: pmpro_limit_logins.ajax_url,
		type:'GET',
		timeout: pmpro_limit_logins.pmpro_limit_logins_ajax_timeout,
		dataType: 'html',
		data: "action=pmpro_limit_logins_check",
		error: function(xml){
			//timeout, but no need to scare the user
		},
		success: function(response){
			response = jQuery.parseJSON(response);
			if( response.flagged ) {
				window.location.href = response.redirect_url;
			}
		},
	});
});