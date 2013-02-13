$(function(){

	$(".chimp-padding input, .chimp-padding select").change( function(){
		$("#chimp-update_settings").attr('checked', true);
	});

	var detailHeight = 120;

	$(".chimp-detail").each(function(i){
		if($(this).height() > detailHeight) { detailHeight = $(this).height(); }
	});

	$(".chimp-detail").css({
		'min-height' : detailHeight + 'px'
		});

});
