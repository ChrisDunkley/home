<?php

require_once 'config.php';
require_once 'lib/MailChimp.php';

/**
 * Response to an Ajax request.
 */
class AjaxResponse {
	
	public $isError;
	public $messages;
	public $data;
    
	
	// Constructor
    public function __construct($isError = false, $messages = array(), $data = array()) {
		if (!is_array($messages)) {
			$messages = array($messages);
		}
		
		$this->isError = $isError;
		$this->messages = $messages;
		$this->data = $data;
	}
	
	
	// Factory method for success responses
	public static function success($messages = array(), $data = array()) {
		return new self(false, $messages, $data);
	}
	
	// Factory method for error responses ($messages can be a string if there is only one message)
	public static function error($messages, $data = array()) {
		return new self(true, $messages, $data);
	}
	
	
	// Build response array
	private function toArray() {
		return array(
			'isError' => $this->isError,
			'messages' => $this->messages,
			'data' => $this->data
		);
	}
	
	// Encode response array to JSON
	public function toJSON() {
		return json_encode($this->toArray());
	}
	
}


/**
 * Simple abstaction of an Ajax request.
 */
abstract class AjaxRequest {
	
	/**
	 * Execute the Ajax request.
	 * An implementation of this method may perform any actions (validation, external API call, etc.)
	 * in any order, but must return an Ajax response (may it be a success or an error).
	 * @return {AjaxResponse} - the response to the request
	 */
	abstract public function execute();
	
}


/**
 * Form submission via Ajax.
 * Features:
 * - sanitise and validate fields,
 * - subscribing to a MailChimp list (optional),
 * - send an email (optional).
 */
abstract class FormSubmission extends AjaxRequest {
	
	/**
	 * The Ajax response object.
	 * @type {AjaxResponse}
	 */
	public $response;
	
	/**
	 * Common form fields and their corresponding validation error messages.
	 * This array should be extended from the constructor of a sub-class when dealing with additional custom fields.
	 * It may also be customised (e.g. custom error messages, custom type for advanced validation).
	 * @type {Array}
	 */
	protected $fields = array(
		'email' => array(
			'type' => 'email',
			'not-provided' => 'Please enter your email address.',
			'invalid' => 'Your email address doesn\'t seem to be valid.'
		),
		'name' => array(
			'type' => 'text',
			'not-provided' => 'Please enter your name.'
		),
		'first' => array(
			'type' => 'text',
			'not-provided' => 'Please enter your first name.'
		),
		'last' => array(
			'type' => 'text',
			'not-provided' => 'Please enter your last name.',
		),
		'org' => array(
			'type' => 'text',
			'not-provided' => 'Please enter the name of your organisation.',
		),
		'phone' => array(
			'type' => 'text',
			'not-provided' => 'Please enter your phone number.',
			'invalid' => 'Your phone number doesn\'t seem to be valid.'
		)
	);
	
	/**
	 * The form validation configuration: an array with fields as keys and sub-arrays as values.
	 * This array must be intialised by sub-classes--either statically or dynamically inside the constructor.
	 * Each key must be one of the fields defined in the `$fields` array.
	 * Each sub-array should contain the validation steps that are to be performed on the field.
	 * An empty array means that no validation is to be performed (only sanitisation).
	 * Available validation steps are: 'not-provided' and 'invalid'.
	 * If a validation step fails, the error message defined in the `$fields` array is added to the response as a message.
	 * @type {Array}
	 */
	protected $config;
	
	/**
	 * The values submitted by the user, sanitised and mapped against the fields.
	 * @type {Array}
	 */
	protected $values = array();
	
	
	/**
	 * Whether to subscribe the user to a MailChimp list when the form is submitted.
	 * If enabled, MailChimp configuration fields (prefixed with `$mc`) must be provided.
	 */
	protected $mailchimp = true;
	
	/**
	 * MailChimp configuration fields.
	 */
	protected $mcListId;
	protected $mcDoubleOptin = false;
	protected $mcUpdateExisting = true;
	protected $mcReplaceInterests = false;
	protected $mcSendWelcome = true;
	protected $mcCustomMergeVars = array();
	
	/**
	 * Whether to send an email when the form is submitted.
	 * If enabled, email configuration fields (prefixed with `$email`) must be provided
	 */
	protected $email = false;
	protected $emailTo = ZENDESK_EMAIL;
	protected $emailFrom;
	protected $emailSubject;
	
	
	/**
	 * If you define the constructor of a sub-class, it must call this constructor:
	 * `parent::__construct();`
	 */
	public function __construct() {
		$this->response = new AjaxResponse();
	}
	
	/**
	 * Execute the Ajax request:
	 * - sanitise and validate the fields,
	 * - subscribe to MailChimp (if enabled)
	 * - send an email (if enabled)
	 * - return a response.
	 */
	public function execute() {
		// Sanitise and validate the fields
		$this->validate();
		
		// If validating the form resulted in any errors, return the response (error messages have been added during validation)
		if ($this->response->isError === true) {
			return $this->response;
		}
		
		// If enabled, subscribe the user to a MailChimp list
		if ($this->mailchimp === true) {
			$this->mailchimp();
			
			// If subscribing to MailChimp resulted in any errors, return the response now
			if ($this->response->isError === true) {
				return $this->response;
			}
		}
		
		// If enabled, send an email
		if ($this->email === true) {
			$this->email();
			
			// If sending the email resulted in any errors, return the response now
			if ($this->response->isError === true) {
				return $this->response;
			}
		}
		
		// If no error occured, add a success message and return the response
		$this->response->messages[] = SUCCESS_MESSAGE;
		return $this->response;
	}
	
	/**
	 * Sanitise and validate the form's fields, according to the configuration (`$config`).
	 */
    protected function validate() {
		foreach ($this->config as $f => $c) {
			// Check that field exists in $field array
			if (!array_key_exists($f, $this->fields)) {
				$this->response->isError = true;
				$this->response->messages[] = 'Unknown field: ' . $f . '.';
			} else {
				$fieldConfig = $this->fields[$f];
				
				// Check that the field is provided as a POST parameter
				if (!isset($_POST[$f])) {
					$this->response->isError = true;
					$this->response->messages[] = 'Field not provided: ' . $f . '.';
				} else {
					// Get field value
					$val = $_POST[$f];
					
					// Retrieve field type from config; if not provided, use 'text'
					$type = isset($fieldConfig['type']) ? $fieldConfig['type'] : 'text';
					
					// Retrieve filters according to field type
					switch ($type) {
						case 'email':
							$sanitizeFilter = FILTER_SANITIZE_EMAIL;
							$validateFilter = FILTER_VALIDATE_EMAIL;
							break;
						case 'url':
							$sanitizeFilter = FILTER_SANITIZE_URL;
							$validateFilter = FILTER_VALIDATE_URL;
							break;
						default:
							$sanitizeFilter = FILTER_SANITIZE_STRING;
							$validateFilter = null;
					}
					
					// Sanitize
					$val = filter_var($val, $sanitizeFilter);
					$this->values[$f] = $val;
					
					// Validate
					$provided = true;
					if (in_array('not-provided', $c) and strlen($val) === 0) {
						$this->response->isError = true;
						$this->response->messages[] = $fieldConfig['not-provided'];
						$provided = false;
					}
					
					// Check for value validity, but only if the field is provided
					if ($provided && $validateFilter !== null and in_array('invalid', $c) and filter_var($val, $validateFilter) === false) {
						$this->response->isError = true;
						$this->response->messages[] = $fieldConfig['invalid'];
					}
				}
			}
		}
					
		// If in debug mode, add the values to the reponse data
		if (AJAX_DEBUG) {
			$this->response->data['values'] = $this->values;
		}
	}
	
	protected function mailchimp() {
    	// Prepare MailChimp object
    	$MailChimp = new MailChimp(MC_ACCOUNT_ID);
		
		// Get sanitized values and change keys to uppercase to match MailChimp merge vars
		$mergeVars = array_change_key_case($this->values, CASE_UPPER);
		
		// Remove empty, optional merge vars values to prevent overwrite 
		// (e.g. if a user registers to VET Commons as a User first and then as a Publisher or vice-versa)
		$mergeVars = array_filter($mergeVars, function ($var) {
			return (strlen($var) > 0);
		});
		
		// Merge custom merge vars
		$mergeVars = array_merge($mergeVars, $this->mcCustomMergeVars);
		
		// Call subscribe method
		$result = $MailChimp->call('lists/subscribe', array(
			'id' => $this->mcListId,
			'email' => array('email' => $this->values['email']),
			'merge_vars' => $mergeVars,
			'double_optin' => $this->mcDoubleOptin,
			'update_existing' => $this->mcUpdateExisting,
			'replace_interests' => $this->mcReplaceInterests,
			'send_welcome' => $this->mcSendWelcome
		));
		
		// If in debug mode, add the result of the API call to the response data
		if (AJAX_DEBUG) {
			$this->response->data['mailchimp'] = $result;
		}
		
		// Check if MailChimp returned an error
		if (isset($result['error'])) {
			$this->response->isError = true;
			if (AJAX_DEBUG) {
				$this->response->messages[] = 'Error while subscribing to MailChimp';
			}
		}
	}
	
	protected function email() {
		// Build the body of the email for the fields and values
		$body = '';
		foreach ($this->values as $key => $value) {
			$body .= "<p><strong>$key</strong>: ";
			if ($this->fields[$key]['type'] === 'textarea') {
				$body .= "<br>";
			}
			$body .= "$value</p>" . PHP_EOL;
		}
		
		// Build headers
		$headers = "From: " . $this->emailFrom . "\r\n";
		$headers = "Reply-To: " . $this->emailFrom . "\r\n";
		$headers .= "MIME-Version: 1.0\r\n";
		$headers .= "Content-Type: text/html; charset=ISO-8859-1\r\n";
		
		// If in debug mode, add the result of the API call to the response data
		if (AJAX_DEBUG) {
			$this->response->data['email'] = array(
				'to' => $this->emailTo,
				'subject' => $this->emailSubject,
				'headers' => $headers,
				'body' => $body
			);
		}
		
		// Send the email
		if (!@mail($this->emailTo, $this->emailSubject, $body, $headers)) {
			$this->response->isError = true;
			if (AJAX_DEBUG) {
				$this->response->messages[] = 'Error while sending the email';
			}
		}
	}
	
}


/**
 * Enquiries (consultation requests).
 */
class Enquiries extends FormSubmission {
	
    // Validation configuration
    protected $config = array(
		'name' => array('not-provided'),
		'org' => array('not-provided'),
		'email' => array('not-provided', 'invalid'),
		'phone' => array('not-provided'),
		'comments' => array(),
		'how' => array()
	);
	
	// Enquiries list ID
	protected $mcListId = ENQUIRIES_LIST_ID;
	
	// Send email
	protected $email = true;
	protected $emailFrom = ENQUIRIES_FROM;
	protected $emailSubject = ENQUIRIES_SUBJECT;
	
	
	public function __construct() {
		parent::__construct();
		
		// Add the 'comments' field of type 'textarea'
		$this->fields['comments'] = array(
			'type' => 'textarea'
		);
		
		// Add the 'how' field of type 'text'
		$this->fields['how'] = array(
			'type' => 'text'
		);
	}
	
}


/**
 * VET Commons User registration or Publisher enquiry.
 */
class VETCommons extends FormSubmission {
	
    // Validation configuration
    protected $config = array(
		'email' => array('not-provided', 'invalid'),
		'first' => array(),
		'last' => array(),
		'org' => array(),
		'phone' => array()
	);
	
	// VET Commons list ID
	protected $mcListId = VETC_LIST_ID;
	protected $mcSendWelcome = false;
	
	// Registration type (User or Publisher)
	private $type;
	
	
	public function __construct() {
		parent::__construct();
		
		// Make sure that a registration type is provided
		if (!isset($_POST['type'])) {
			$this->response->isError = true;
			$this->response->messages[] = 'Registration type not provided.';
		} else {
			// Get registration type
			$this->type = filter_var($_POST['type'], FILTER_SANITIZE_STRING);

			// Change validation configuration depending on registration type
			switch ($this->type) {
				case 'user':
					// If user, add custom field 'moodleurl'
					$this->fields['moodleurl'] = array(
						'type' => 'url',
						'not-provided' => 'Please enter your Moodle URL.',
						'invalid' => 'Your Moodle URL doesn\'t seem to be valid.'
					);

					// ... and validate it
					$this->config['moodleurl'] = array('not-provided', 'invalid');
					
					// Add custom merge var 'TOKENSET'
					$this->mcCustomMergeVars['TOKENSET'] = 'No';
					break;

				case 'publisher':
					// If publisher, valdiate Organisation field
					$this->config['org'] = array('not-provided');
					break;

				default:
					$this->response->isError = true;
					$this->response->messages[] = 'Invalid registration type: ' . $this->type;
			}
			
			// Add registration type grouping to custom merge vars
			$this->mcCustomMergeVars['groupings'] = array(array(
				'id' => 15021,
				'groups' => array(ucfirst($this->type))
			));
		}
	}
	
}


/**
 * eLink subscription.
 */
class eLink extends FormSubmission {
	
    // Validation configuration
    protected $config = array(
		'email' => array('not-provided', 'invalid')
	);
	
	// eLink/Flex list ID
	protected $mcListId = ELINK_LIST_ID;
	
	public function __construct() {
		parent::__construct();
		
		// Enable double opt-in
		$this->mcDoubleOptin = true;
		
		// Change error message when email is not valid
		$this->fields['email']['invalid'] = "This address doesn't seem to be valid.";
	}
	
}


/**
 * Consultant enquiry.
 */
class Consultant extends FormSubmission {
	
    // Validation configuration
    protected $config = array(
		'first' => array('not-provided'),
		'last' => array('not-provided'),
		'org' => array(),
		'phone' => array('not-provided'),
		'email' => array('not-provided', 'invalid')
	);
	
	// Consultants list ID
	protected $mcListId = CONSULTANTS_LIST_ID;
	protected $mcSendWelcome = false;
	
	// Send email
	protected $email = true;
	protected $emailFrom = CONSULTANTS_FROM;
	protected $emailSubject = CONSULTANTS_SUBJECT;
    
    
	public function __construct() {
		parent::__construct();
		
		// Add registration type grouping to merge vars
		$this->mcCustomMergeVars['groupings'] = array(array(
			'id' => 15029,
			'groups' => array('Prospective')
		));
    }
	
}


/**
 * Ajax request factory (singleton)
 */
final class AjaxRequestFactory {
	
	public static function getInstance() {
		static $inst = null;
		if ($inst === null) {
			$inst = new AjaxRequestFactory();
		}
		return $inst;
	}
	
    public function createRequest($action) {
		$request = null;
		
		switch ($action) {
			case 'enquiries':
				$request = new Enquiries();
				break;
			case 'vetcommons':
				$request = new VETCommons();
				break;
			case 'elink':
				$request = new eLink();
				break;
			case 'consultant':
				$request = new Consultant();
				break;
			default:
		}
		
		return $request;
	}
	
}


// If 'action' parameter is not provided, exit with error
if (!isset($_POST['action'])) {
	$response = AjaxResponse::error('Action not provided.');

// Otherwise, create and execute the appropriate request for the action
} else {
	$action = $_POST['action'];
	
	$factory = AjaxRequestFactory::getInstance();
	$request = $factory->createRequest($action);

	// If the request has been created successfully and no error occured, execute the request
	if ($request !== null) {
		if ($request->response->isError === false) {
			$response = $request->execute();
		} else {
			$response = $request->response;
		}
	} else {
		$response = AjaxResponse::error('Invalid action: ' . $action . '.');
	}
}

echo $response->toJSON();
exit()

?>