jQuery.noConflict();
jQuery(document).ready(function($){

	$('.page_links_too').on( 'click', 'input[type="radio"]', function(){
		$('.plt_expander').hide();
		$(this).closest('.plt_section').find('.plt_expander').show();
	});

	$('.page_links_too input[type=radio]:checked').closest('.plt_section').find('.plt_expander').show();

});