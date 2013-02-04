$(function(){

	$(".mailchimp input, .mailchimp select").change( function(){
		$("#mailchimp-update_settings").attr('checked', true);
	});

});