$(function(){

	$(".mailchimp input, .mailchimp select").change( function(){
		$("#update_settings").attr('checked', true);
	});

});