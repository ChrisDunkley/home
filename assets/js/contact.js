
$(function () {
	"use strict";
	
	var $contact = $("#contact");
	
	$contact.ajaxLoader({
		success: function () {
			// Hide form
			$contact.find('.contact-form').addClass("hidden");
		},
	});
	
});
