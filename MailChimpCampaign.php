<?php

 /**
 * MailChimpCampaign class
 *
 * Class to hold a FieldtypeMailChimpCampaign and handles logic & markup  & for the
 * ___render() methode in the InputfieldMailChimpCampaign.
 *
 * Copyright (C) 2013 by Martijn Geerts
 * Web: http://www.agrio.nl
 *
 * Special Thanks to: Ryan & Soma. This Fieldtype & Inputfield borrows a lot of cleverness
 * written in the MapMarker and Soma's slider.
 *
 * ProcessWire 2.x
 * Copyright (C) 2013 by Ryan Cramer
 * Licensed under GNU/GPL v2, see LICENSE.TXT
 *
 * http://www.processwire.com
 *
 */

class MailChimpCampaign extends WireData {

	/**
	 * Construct the class
	 *
	 */
	public function __construct() {
		// include a path to the MailChimp API class
		require_once(dirname(__FILE__) . '/MCAPI.class.php');
	}

	/**
	 * Method for other classes to set data to this->data in this class.
	 * See ___render() methode in InputfieldMailChimpCampaign.
	 *
	 * @param string $key row name in db, fieldname
	 * @param mixed $value string or object
	 *
	 * @return object Field object
	 */
	public function set($key, $value) {
		return parent::set($key, $value);
	}

	/**
	 * Get value by key.
	 *
	 * @param string $key get field value by key
	 * @return object Field object
	 */
	public function get($key) {
		return parent::get($key);
	}

	/**
	 * Throw bananas! (incorrect MailChimp API key, lacking/wrong Campaign id, can't
	 * create campaign )
	 *
	 * @return string markup for error
	 */
	public function errorMessage() {
		$out = "<p class='message'>" . $this->_("Bananas.... ") . "</p>";
		return $out;
	}

	/**
	 * chimpsError handles custom & MailChimp errors.
	 *
	 * @param int $code code provided via MailChimp or a custom code number
	 * @param string $message message provided via MailChimp or a custom message
	 */
	public function chimpsError($code, $message=null) {

		if(!$code) return false;

		$array = array(
			// error numbers provided via the MailChimp API, triggered in InputfieldMailChimpCampaign
			'104' => $this->_("Looks like your <a href='" . wire('config')->urls->admin . "module/edit?name=Inputfield{$this->className}'>Mailchimp API Key</a> is empty or invalid."),
			'200' => $this->_('The mail list doesn\'t exist, did someone delete it on mailchimp?'),
			'300' => $this->_('Oops, that campaign doesn\'t exist.'),
			'301' => $this->_('The Mailchimp monkey is real quiet, he hasn\'t any details for you.'),
			'311' => $this->_('Chimp\'s waiting. Please provide all information.'),

			// custom error(s)
			'1000' => $this->_("Please give the monkey an <a href='" . wire('config')->urls->admin . "module/edit?name=Inputfield{$this->className}'>API Key</a>."),
			'1001' => $this->_("Please give the chimp a valid <a href='" . wire('config')->urls->admin . "module/edit?name=Inputfield{$this->className}'>API Key</a>."),
			);

		$message = isset($array[$code]) ? $array[$code] : $message;
		$this->error($message);

		return true;
	}

	/**
	 * A basic methode returns true if the "front-page" is unblished.
	 *
	 * @return bool true or false
	 */
	public function statusUnpublished() {
		if($this->frontpage->is(Page::statusUnpublished)) return true;
		return false;
	}

	/**
	 * Prepare is a method that collects data needed to create a MailChimp campaign.
	 * We need at least a list_id, subject, from_name and a from_email.
	 *
	 * contain logic
	 */
	public function prepare() {

		// if the page is not published, no need contact the chimp.
		if($this->statusUnpublished()) {
			$this->message($this->_("The chimp can't find bananas here. Please Publish the page."));
		}

		// return the prepare markup
		return $this->markupPrepare();
	}

	/**
	 * The API key is checked and is valid. We now ask the chimp to create a campaign for
	 * us & give a campaign id back. This id wil stay the same. It's the link with the
	 * campaign on MailChimp
	 *
	 * contain logic
	 */
	public function create() {

		// if the page is not published, no need contact the chimp.
		if($this->statusUnpublished()) {
			$this->message($this->_("The chimp can't find bananas here. Please Publish the page."));
			return $this->markupCampaign();
		}

		// wake-up the monkey
		$api = new MCAPI($this->apiKey);

		// reference the this field
		$name = $this->get('name');
		$value = $this->get('value');

		/**
		 * Basic variable required by mailchimp
		 *
		 */

		$type = 'regular';

		$opens = $value->opens == 1 ? true : false;
		$html_clicks = $value->html_clicks == 1 ? true : false;
		$text_clicks = $value->text_clicks == 1 ? true : false;

		$opts['tracking'] = array(
			'opens' => $opens,
			'html_clicks' => $html_clicks,
			'text_clicks' => $text_clicks
			);

		$opts['title'] = $this->frontpage->title;
		$opts['list_id'] = $value->list;
		$opts['subject'] = $value->subject;
		$opts['from_email'] = $value->from_email;
		$opts['from_name'] =  $value->from_name;

		$content = array(
			'url'=> $this->frontpage->httpUrl,
			'text' => '*|UNSUB|*',
			);

		// tracking ( note: fake bools)
		$opens = $value->get('opens') == 1 ? 'true' : 'false';
		$html_clicks = $value->get('html_clicks') == 1 ? 'true' : 'false';
		$text_clicks = $value->get('text_clicks') == 1 ? 'true' : 'false';

		$options = array(
			'opens' => $opens,
			'html_clicks' => $html_clicks,
			'text_clicks' => $text_clicks
			);

		$api->campaignUpdate( $value->id, 'tracking', $options);

		$campaign_id = $api->campaignCreate($type, $opts, $content);

		// what is going on ?
		if ($api->errorCode) {
			// show them rotten bananas
			$out = $this->markupPrepare();
			// and let the monkey tell us what has happened.
			$this->chimpsError($api->errorCode, $api->errorMessage);
			return $out;
		}

		$value->id = $campaign_id;
		$this->set($this->name, $campaign_id);

		return $this->markupCampaign();
	}

	/**
	 * update request or bypass MailChimp
	 *
	 * contain logic
	 */
	public function update() {

		// if the page is not published, no need contact the chimp.
		if($this->statusUnpublished()) {
			$this->message($this->_("The chimp can't find bananas here. Please Publish the page."));
			return $this->markupCampaign();
		}

		// provide easy access
		$value = $this->get('value');

		// The state of the checkbox is always unchecked, unless you check the box or
		// when the fields are changed. Then jQuery will check the box.
		if ($value->update_settings == false ) return $this->markupCampaign();

		// wake-up the monkey
		$api = new MCAPI($this->apiKey);

		// update settings
		$api->campaignUpdate( $value->id, 'title', $value->campaign_title);
		$api->campaignUpdate( $value->id, 'subject', $value->subject);
		$api->campaignUpdate( $value->id, 'from_email', $value->from_email);
		$api->campaignUpdate( $value->id, 'from_name', $value->from_name);
		$api->campaignUpdate( $value->id, 'list_id', $value->list);

		// content
		$options = array(
			'url' => $this->frontpage->httpUrl,
			);

		$api->campaignUpdate( $value->id, 'content', $options);

		// tracking ( note: fake bools)
		$opens = $value->get('opens') == 1 ? 'true' : 'false';
		$html_clicks = $value->get('html_clicks') == 1 ? 'true' : 'false';
		$text_clicks = $value->get('text_clicks') == 1 ? 'true' : 'false';

		$options = array(
			'opens' => $opens,
			'html_clicks' => $html_clicks,
			'text_clicks' => $text_clicks
			);

		$update = $api->campaignUpdate( $value->id, 'tracking', $options);

		// Oops, something is wrong. Let the monkey speak to us.
		if ($api->errorCode) {
			// show them bananas
			//$out = $this->errorMessage();

			echo $api->errorCode;
			$out = $this->markupCampaign();

			// and let the monkey speak to us.
			$this->chimpsError($api->errorCode, $api->errorMessage);
			if( $api->errorCode == 300 ) {
				// empty the list (to return to the prepare state) and clean the id cause
				// it is not available at mailchimp anymore. (campaign is deleted)
				$out .= "<input type='hidden' name='list' value='' />\n";
				$out .= "<input type='hidden' name='{$name}' value='' />\n";
			}
			return $out;
		}


		$this->message($this->_("The settings are updated on MailChimp."));

		$out = $this->markupCampaign();
		return $out;
	}

	/**
	 * Method to find all mailing lists. Returns an array with all lists
	 *
	 * @return array array with lists returned bij MailChimp
	 */
	public function lists() {

		// Instantiate the class provided by MailChimp.
		$api = new MCAPI($this->apiKey);

		// Create variable for the MailChimp List.
		$lists = $api->lists();

		// Oops, something is wrong. Let the monkey speak to us.
		if ($api->errorCode) $this->chimpsError($api->errorCode, $api->errorMessage);
		if ($api->errorCode) return null;

		// Check if there is a list.
		if(count($lists['data']) === 0) {
			$message = $this->_("You should create a mailinglist in MailChimp first");
			$this->message($message);
		}

		// Check if there is a list.
		if(count($lists['data'])) return $lists['data'];
	}

	/**
	 * markupInputfield outputs the markup for the inputfield
	 *
	 * @return string markup for the first step, collecting data needed for creating a
	 * 	campaign
	 */
	public function markupPrepare() {

		$out = '';

		// reference the this field
		$name = $this->get('name');
		$value = $this->get('value');
		
		$out .= "<h2 class='chimp'>".$this->_('This is the first step before we ask the chimp to create a campaign for us.')."</h2>".
		"<p>".$this->_('All fields below are required by MailChimp to create a campaign. After the campaign is created, we receive the campaign id and we\'re good to go.')." ". 
		$this->_('The campaign ID is the only field you can\'t update afterwards.')."</p>";

		$out .= $this->markupCheckbox('opens', $this->_('1'));
		$out .= $this->markupCheckbox('html_clicks', $this->_('2'));
		$out .= $this->markupCheckbox('text_clicks', $this->_('3'));

		$title = empty($value->campaign_title) ? $this->frontpage->title : $value->campaign_title;

		// Columns output
		$out .= 
		"<div class='chimp-row chimp cf'>".
		$this->markupInput('campaign_title', $title, $this->_("Name Your Campaign"), $this->_("Only used by MailChimp internal.")).
		$this->markupSelect($this->lists()).
		$this->markupInput('subject', $value->subject, $this->_("Email Subject"),  $this->_("The subject line for your campaign message")).
		$this->markupInput('from_name', $value->from_name, $this->_("From"), $this->_("Name. (not email address)")).
		$this->markupInput('from_email', $value->from_email, $this->_("Reply-To"), $this->_("Email address for your campaign message.")).
		"</div>";

		$out .= "<input type='hidden' name='{$name}' value='{$value->id}' />";

		$out .= "<p class='notes'>{$this->_('All fields above are required, but you can change them later if you wish.')}</p>";

		return $out;
	}

	/**
	 * markupInputfield outputs the markup for the campaign inputfield
	 *
	 * @return string markup for created & update campaign
	 */
	public function markupCampaign() {

		$out = '';

		// reference the this field
		$name = $this->name;
		$value = $this->value;
		
		// checkboxes
		$out .= 
		$this->markupCheckbox('opens', $this->_("Track Opens")).
		$this->markupCheckbox('html_clicks', $this->_('Track Clicks')).
		$this->markupCheckbox('text_clicks', $this->_('Track Plain-Text Clicks'));

		// Columns output
		$out .= 
		"<div class='chimp-row chimp cf'>".
		$this->markupInput('campaign_title', $this->frontpage->title, $this->_("Name Your Campaign"), $this->_("Only used by MailChimp internal.")).
		$this->markupSelect($this->lists()).
		$this->markupInput('subject', $value->subject, $this->_("Email Subject"),  $this->_("The subject line for your campaign message")).
		$this->markupInput('from_name', $value->from_name, $this->_("From"), $this->_("Name. (not email address)")).
		$this->markupInput('from_email', $value->from_email, $this->_("Reply-To"), $this->_("Email address for your campaign message.")).
		$this->markupInput('id', $value->id, $this->_("Campaign id"), $this->_("Can't be changed."), $enabled=false).
		"</div>";

		$out .=	"<input type='hidden' name='{$name}' value='{$value->id}' />\n";

		$out .="<p class='detail'>".
		"	<label for='chimp-update_settings'>".
		"		<input type='checkbox' value='1' id='chimp-update_settings' name='update_settings' />".
		"		Update on MailChimp".
		"	</label>".
		"</p>";

		return $out;
	}

	/**
	 * markupInput outpus markup for a html form field.
	 *
	 * @param string $fieldName name of the field
	 * @param string $fieldValue value of the field
	 * @param string $fieldLabel name for the label
	 * @param string $fieldDescription description of the field
	 * @param bool $enabled true/false switch the name property with disabled property.
	 *  needed for the campaign id. default true
	 * @return string markup for an input field
	 */
	public function markupInput($fieldName, $fieldValue, $fieldLabel='', $fieldDescription, $enabled=true) {
		if(empty($fieldName)) return false;
		$modules = wire('modules');
		$wrapper = $modules->get('InputfieldFieldset');
		$field = $fieldName == 'from_email' ? $modules->get('InputfieldEmail') : $modules->get('InputfieldText');
		// visual campaign id field should be always disabled
		if(!$enabled) $field->attr('disabled','disabled');
		// don't give a name fort disabled campaign id field
		$field->name = $enabled === true ? $fieldName : '';
		// change type for email field
		$field->value = $fieldValue;
		$field->label = $fieldLabel;
		$field->description = $fieldDescription;
		$field->notes = "\$page->{$this->get('name')}->{$fieldName}";
		$wrapper->append($field);
		$wrapper->append($field);
				
		$out = "<div class='chimp-column'>".
		"		<div class='chimp-padding'>".
		 			$wrapper->render().
		"		</div>".
		"	</div>";
		
		return $out;
	}

	/**
	 * markupSelect takes an array create by the lists(). Outputs a select field
	 *
	 * @param array array with key => value, listid, name of the list
	 * @return string markup for an select field
	 */
	public function markupSelect($array) {
		$value = $this->get('value');
		$modules = wire('modules');
		$wrapper = $modules->get('InputfieldFieldset');
		$field = $modules->get('InputfieldSelect');
		$field->name = 'list';
		// change type for email field
		$field->label = $this->_('Mailing list');
		$field->description = $this->_('The list to send this campaign to.');
		$field->notes = "\$page->{$this->get('name')}->list";
		foreach($array as $option)	{
			$attr = $value->list == $option['id'] ? array('selected' => 'selected') : null;
			$field->addOption($option['id'], $option['name'], $attr);
		}
		$wrapper->append($field);
		
		$out = "<div class='chimp-column'>".
		"		<div class='chimp-padding'>".
		 			$wrapper->render().
		"		</div>".
		"	</div>";
		
		return $out;
	}

	/**
	 * markupCheckbox
	 *
	 * @param string $fieldName name of the field
	 * @param string $fieldLabel label for the field
	 * @return string markup for a checkbox
	 */
	public function markupCheckbox($fieldName, $fieldLabel, $info=null) {
		$value = $this->get('value');
		$modules = wire('modules');
		$field = $modules->get('InputfieldCheckbox');
		$field->name = $fieldName;
		$field->value = $value->get($fieldName);
		$field->checkedValue = 1;
		$field->label = $fieldLabel;
		if ( $value->get($fieldName) == 1 ) $field->attr('checked', 'checked');
		return $field->render();
	}
}
