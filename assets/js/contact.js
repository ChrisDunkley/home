
$(function () {
	"use strict";
	
	var $contact = $("#contact");
	
	$contact.ajaxLoader({
		success: function () {
			// Hide form
			$contact.find('.contact-form').addClass("hidden");
		},
		gaSuccess: ['event', 'contact form', 'submit'],
		gaError: ['event', 'contact form', 'error']
	});
	
});
