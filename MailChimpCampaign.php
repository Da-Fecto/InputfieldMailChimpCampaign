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
	 * - Include the MailChimp API class
	 * - Provide some information from the "front" Page
	 *
	 */

	public function __construct() {
		// include a path to the MailChimp API class
		require_once(dirname(__FILE__) . '/MCAPI.class.php');

		$pageId = (int) wire('input')->get('id');
		$page = wire('pages')->get($pageId);
		
		// url & campaign_title needed in 
		$this->set('url', $page->httpUrl);
		$this->set('campaign_title', $page->title);
		// needed by statusUnpublished
		$this->set('page', $page);
	}

	/**
	 * Method for other classes to set data to this->data in this class.
	 * See ___render() methode in InputfieldMailChimpCampaign.
	 *
	 */

	public function set($key, $value) {
		return parent::set($key, $value);
	}

	/**
	 * 	Get value by key.
	 *
	 */

	public function get($key) {
		return parent::get($key);
	}

	/**
	 * Throw bananas! 
	 * (incorrect MailChimp API key, lacking/wrong Campaign id, can't create campaign )
	 *
	 */

	public function errorMessage() {
		$out = "<p class='message'>" . $this->_("Bananas.... ") . "</p>";
		return $out;
	}

	/**
	 * chimpsError handles custom & MailChimp errors.
	 *
	 */

	public function chimpsError($code, $message=null) {

		if(!$code) return false;

		$array = array(
			// error numbers provided via the MailChimp API, triggered in InputfieldMailChimpCampaign
			'104' => $this->_("Looks like your <a href='" . wire('config')->urls->admin . "module/edit?name=Inputfield{$this->className}'>Mailchimp API Key</a> is empty or invalid."),
			'200' => $this->_('The mail list doesn\'t exist, did someone delete it on mailchimp?'),
			'300' => $this->_('Oops, that campaign doesn\'t exist.'),
			'301' => $this->_('The Mailchimp monkey is real quiet, he hasn\'t any details for you.'),

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
	 */

	public function statusUnpublished() {
		if($this->page->is(Page::statusUnpublished)) return true;
		return false;
	}

	/**
	 * Prepare is a method that collects data needed to create a MailChimp campaign.
	 * We need at least a list_id, subject, from_name and a from_email. Campaign title
	 * is optional.
	 *
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

		$opts['tracking'] = array(
			'opens' => true,
			'html_clicks' => true,
			'text_clicks' => false
			);

		$opts['title'] = $value->campaign_title;
		$opts['list_id'] = $value->list;
		$opts['subject'] = $value->subject;
		$opts['from_email'] = $value->from_email;
		$opts['from_name'] =  $value->from_name;

		$content = array(
			'url'=> $this->url,
			'text' => '*|UNSUB|*',
			);

		$campaign_id = $api->campaignCreate($type, $opts, $content);

		// what is going on ?
		if ($api->errorCode) {
			// show them rotten bananas
			$out = $this->errorMessage();
			// and let the monkey tell us what has happened.
			$this->chimpsError($api->errorCode, $api->errorMessage);
			return $out;
		}

		$value->id = $campaign_id;
		$this->set($this->name, $campaign_id);

		return $this->markupCampaign();
	}

	/**
	 * Update
	 *
	 */

	public function update() {

		// if the page is not published, no need contact the chimp.
		if($this->statusUnpublished()) {
			$this->message($this->_("The chimp can't find bananas here. Please Publish the page."));
			return $this->markupCampaign();
		}

		// provide easy access
		$value = $this->value;
		
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

		// change url
		$options = array();
		$options['url'] = $this->url;
		$api->campaignUpdate( $value->id, 'content', $options);

		// Oops, something is wrong. Let the monkey speak to us.
		if ($api->errorCode) {
			// show them bananas
			$out = $this->errorMessage();
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
	 * This methode markupInputfield outputs the markup for the inputfield
	 *
	 */

	public function markupPrepare() {

		// reference the this field
		$name = $this->get('name');
		$value = $this->get('value');

		$out = "<h2 class='mailchimp'>" . $this->_('This is the first step for creating a campaign in MailChimp.') . "</h2>\n".
		"<p>" . $this->_('All fields below are required by mailchimp to create a campaign. After the campaign is created, we receive the Campaign ID and we store it in our field.').
		" " . $this->_('The campaign ID is the only field you can\'t update afterwards.') . "</p>\n";

		// Columns output
		$out .= "<div class='mailchimp-row mailchimp cf'>\n".
		"		<div class='mailchimp-column'>\n".
		"			<div class='mailchimp-padding'>\n";
		// Name Your Campaign & From name
		$title = empty($value->campaign_title) ? $this->campaign_title : $value->campaign_title;
		$out .= $this->markupInput('campaign_title', $title, $this->_("Name Your Campaign"), $this->_("Only used by MailChimp internal."));
		$out .= $this->markupInput('from_name', $value->from_name, $this->_("From"), $this->_("Name. (not email address)"));
		$out .=	"	</div>\n".
		"		</div>\n".
		"		<div class='mailchimp-column'>\n".
		"			<div class='mailchimp-padding'>\n";
		// Mailing list Reply-To Email Address
		$out .=	count($this->lists()) ? $this->markupSelect($this->lists()) : '';
		$out .= $this->markupInput('from_email', $value->from_email, $this->_("Reply-To"), $this->_("Email address for your campaign message."));
		$out .=	"	</div>\n".
		"		</div>\n".
		"		<div class='mailchimp-column'>\n".
		"			<div class='mailchimp-padding'>\n";
		// Email Subject
		$out .= $this->markupInput('subject', $value->subject, $this->_("Email Subject"),  $this->_("The subject line for your campaign message"));
		$out .=	"	</div>\n".
		"		</div>\n".
		"	</div>\n".
		"	<input type='hidden' name='{$name}' value='{$value->id}' />\n";

		$out .= "<p class='notes'>{$this->_('All fields above are required when creating a campaign. After you saved the page, we wil ask the chimp to create a campaign for us.')}</p>";

		return $out;
	}

	/**
	 * This methode markupInputfield outputs the markup for the campaign inputfield
	 *
	 */

	public function markupCampaign() {

		// reference the this field
		$name = $this->get('name');
		$value = $this->get('value');

		// Columns output
		$out .= "<div class='mailchimp-row mailchimp cf'>\n".
		"		<div class='mailchimp-column'>\n".
		"			<div class='mailchimp-padding'>\n";
		// Name Your Campaign &	From name
		$title = empty($value->campaign_title) ? $this->campaign_title : $value->campaign_title;
		$out .= $this->markupInput('campaign_title', $title, $this->_("Name Your Campaign"), $this->_("Only used by MailChimp internal."));
		$out .= $this->markupInput('from_name', $value->from_name, $this->_("From"), $this->_("Name. (not email address)"));
		$out .=	"	</div>\n".
		"		</div>\n".
		"		<div class='mailchimp-column'>\n".
		"			<div class='mailchimp-padding'>\n";
		// Mailing list & Reply-To Email Address
		$out .=	count($this->lists()) ? $this->markupSelect($this->lists()) : '';
		$out .= $this->markupInput('from_email', $value->from_email, $this->_("Reply-To"), $this->_("Email address for your campaign message."));
		$out .=	"	</div>\n".
		"		</div>\n".
		"		<div class='mailchimp-column'>\n".
		"			<div class='mailchimp-padding'>\n";
		// Email Subject & Campaign id
		$out .= $this->markupInput('subject', $value->subject, $this->_("Email Subject"),  $this->_("The subject line for your campaign message"));
		$out .= $this-> markupInput('id', $value->id, $this->_("Campaign id"), $this->_("Can't be changed."), $enabled=false);
		$out .=	"	</div>\n".
		"		</div>\n".
		"	</div>\n".
		"	<input type='hidden' name='{$name}' value='{$value->id}' />\n";

		$out .="<p class='detail'><label for='update_settings'>".
		"<input type='checkbox' value='update' id='update_settings' name='update_settings' /> ".
		"Update the settings on mailchimp".
		"</label></p>";

		return $out;
	}


	/**
	 * markupInput outpus markup for a html form field.
	 *
	 */

	public function markupInput($fieldName, $fieldValue, $fieldLabel='', $fieldDescription, $enabled=true) {

		if(empty($fieldName)) return;

		// reference the this field
		$name = $this->get('name');
		$type = $fieldName == 'from_email' ? 'email' : 'text';

		$out = "<div class='ui-widget'>\n".
		"	<label class='ui-widget-header' for='mailchimp-{$fieldName}'>" .$this->_($fieldLabel) ."</label>\n".
		"	<div class='ui-widget-content'>\n".
		"		<p class='description'>{$fieldDescription}</p>\n".
		"		<input type='{$type}' id='mailchimp-{$fieldName}'";
		$out .=	$enabled === true ? " name='{$fieldName}' " : " disabled='disabled' ";
		$out .= 	"value='{$fieldValue}' />\n".
		"		<br />\n".
		"		<span class='detail'>\$page->{$name}->{$fieldName}</span>\n".
		"	</div>\n".
		"</div>";

		return $out;
	}

	/**
	 * markupSelect takes an array create by the methode lists(). Outputs a select field
	 *
	 */

	public function markupSelect($array) {

		if(!count($array)) return;

		// reference the this field
		$name = $this->get('name');
		$value = $this->get('value');

		$out =  "<div class='ui-widget'>\n".
		"	<label class='ui-widget-header' for='mailchimp-list_id'>{$this->_('Mailing list')}</label>\n".
		"	<div class='ui-widget-content'>\n".
		"		<p class='description'>{$this->_('The list to send this campaign to.')}</p>\n".
		"		<select id='mailchimp-list_id' name='list'>\n";
		$out .= "<option value='' disabled='disabled'>\n". $this->_('Please select a list...') . "</option>";		
		// loop the array 
		foreach($array as $option) {
			$selected = $value->list == $option['id'] ? ' selected="selected" ' : '';			
			$out .= "<option value='{$option['id']}'{$selected}>{$option['name']}</option>";
		}
		$out .= "		</select>\n".
		"		<br />\n".
		"		<span class='detail'>\$page->{$name}->list</span>\n".
		"	</div>\n".
		"</div>";

		return $out;
	}
}
