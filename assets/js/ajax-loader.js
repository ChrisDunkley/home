/**
 * Generic Ajax loading script. Included features:
 * - show/hide loading spinner,
 * - call web service,
 * - show/hide success and failure containers,
 * - display error messages,
 * - parse response as JSON,
 * - compile and evaluate Handlebar template with response.
 * 
 * To use this loader for submitting a form, simply call `$elem.ajaxLoader();` on the form itself or on one of its parents.
 * The form must have class `ajax-form`.
 * The root element should have descendants with classes: `ajax-(spinner|success|fail|errors)`.
 * 
 * To use this loader for calling a web service and pass the response to a template, call $elem.ajaxLoader(options);` on any element.
 * In the options object, provide the URL of the web service and optional function hooks (`before`, `processData`, `always`, `done` `fail`).
 * The root element should have descendants with classes: `ajax-(body|template|spinner|success|fail)`.
 */
(function (d, $) {
	"use strict";
	
	var defaults = {
		// XHR config
		url: "/php/Ajax.php",
		httpMethod: "post",
		responseFormat: "json",
		
		// Function hook called with the response data, before the template is evaluated
		// The function must return the processed data as an object
		processData: null,
		
		// Function hooks
		beforeAjax: null, // before making Ajax call
		ajaxAlways: null, // on Ajax response, whether or not it succeeds
		ajaxDone: null, // on successful Ajax response (HTTP code 200)
		ajaxFail: null, // on failed Ajax response (e.g. 404, 500, etc.)
		error: null, // if successful Ajax response describes an error
		success: null, // if successful Ajax response does not describe an error
		
	};
	
	function AjaxLoader(elem, options) {
		this.elem = elem;
		this.$elem = $(elem);
		this.settings = $.extend({}, defaults, options, this.$elem.data());
		
		this.$spinner = this.$elem.find(".ajax-spinner");
		this.$success = this.$elem.find(".ajax-success");
		this.$fail = this.$elem.find(".ajax-fail");
		this.$errors = this.$elem.find(".ajax-errors");
		
		// Templating
		this.$body = this.$elem.find(".ajax-body");
		var $template = this.$elem.find(".ajax-template");
		if ($template.length > 0) {
			this.template = Handlebars.compile($template.html());
		}
		
		// Retrieve the form, if there is any
		this.$form = this.$elem.is(".ajax-form") ? this.$elem : this.$elem.find(".ajax-form");
		
		if (this.$form.length > 0) {
			// If a form exists, capture the submit event
			this.$form.submit($.proxy(function (evt) {
				evt.preventDefault();
				
				// Blur submit button
				this.$form.find('input[type="submit"], button[type="submit"]').blur();
				
				// Perform the Ajax request
				this.init(this.$form.serialize());
			}, this));
		} else {
			// Otherwise, perform the Ajax request right away
			this.init();
		}
	}
	
	AjaxLoader.prototype = {
		
		init: function (data) {
			// Return if a URL is not provided
			if (!this.settings.url) {
				return;
			}
			
			// Hide all elements, in case this is not the first time the loader is called
			this.$success.addClass("hidden");
			this.$fail.addClass("hidden");
			
			// Show the spinner
			this.$spinner.removeClass("hidden");
			
			// `beforeAjax` hook
			this.callHook('beforeAjax');
			
			// Perform the Ajax request
			$.ajax({
				type: this.settings.httpMethod,
				dataType: this.settings.responseFormat,
				url: this.settings.url,
				data: data
			})
			.always($.proxy(this.always, this))
			.done($.proxy(this.done, this))
			.fail($.proxy(this.fail, this));
		},
		
		always: function () {
			// `ajaxAlways` hook
			this.callHook('ajaxAlways');
		},
		
		done: function (response) {
			// `ajaxDone` hook
			this.callHook('ajaxDone');
			
			// Hide the spinner
			this.$spinner.addClass("hidden");
			
			// Web service returned an error (e.g. form validation)
			if (typeof response.error !== "undefined" || response.isError === true) {
				// If a container is available to append the error message(s), do so
				if (this.$errors.length > 0 && $.isArray(response.messages) && response.messages.length > 0) {
					var html = "";
					$.each(response.messages, $.proxy(function(index, value) {
						html += "<p>" + value + "</p>";
					}, this));
					
					// Append the errors
					this.$errors[0].innerHTML = html;
					
					// Give focus to the first error
					this.$errors.removeClass("hidden").attr("tabindex", -1).focus();
				} else {
					// Otherwise, show the failure container
					this.error();
				}
				
				// `error` hook
				this.callHook('error');
				return;
			}
			
			// Retrieve data from response
			var data = response.data ? response.data : response;
			
			// Call `processData` function if given
			if (this.settings.processData) {
				data = this.settings.processData(data);
			}

			// Evaluate template with response and populate body
			if (this.template) {
				this.$body.append(this.template(data));
			}
			
			// Show the success container and hide the errors
			this.$errors.addClass("hidden");
			this.$success.removeClass("hidden");
			
			// `success` hook
			this.callHook('success');
		},
		
		fail: function () {
			// `ajaxFail` hook
			this.callHook('ajaxFail');
			
			// Show failure container
			this.error();
		},
		
		error: function () {
			// Hide the spinner and the errors, and show the failure container
			this.$spinner.addClass("hidden");
			this.$errors.addClass("hidden");
			this.$fail.removeClass("hidden").attr("tabindex", -1).focus();
		},
		
		// Call a function hook with optional data
		callHook: function (hookName, data) {
						
			// If function hook provided, call it
			var hook = this.settings[hookName];
			if (hook) {
				hook(data);
			}
		}
		
	};
	
	$.fn.ajaxLoader = function (options) {
		return this.each(function () {
			new AjaxLoader(this, options);
		});
	};
	
}(document, jQuery));
