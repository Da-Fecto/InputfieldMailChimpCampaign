<?php

// make sure all errors get reported regardless of what ini-files might contain
error_reporting(E_ALL | E_STRICT);

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
	 * set status of the page
	 *
	 */
	public $ChimpStatus = null;

	/**
	 * Construct the class
	 *
	 */
	public function __construct() {
		// include a path to the MailChimp API class
		require_once(dirname(__FILE__) . '/MCAPI.class.php');
		$this->set('status', null);
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
	 * contains logic
	 */
	public function prepare() {
		
		// if the page is not published, no need contact the chimp.
		if($this->statusUnpublished()) {
			$this->message($this->_("The chimp can't find bananas here. Please publish the page."));
		}
		// return the prepare markup
		$this->ChimpStatus = 'prepare';

		// reference the this field
		$name = $this->get('name');
		$value = $this->get('value');
		
		$value->opens = $this->setOpens;
		$value->html_clicks = $this->setHtml_clicks;
		$value->text_clicks = $this->setText_clicks;
				
		return $this->markupPrepare();
	}

	/**
	 * The API key is checked and is valid. We now ask the chimp to create a campaign for
	 * us & give a campaign id back. This id wil stay the same. It's the link with the
	 * campaign on MailChimp
	 *
	 * containslogic
	 */
	public function create() {

		// if the page is not published, no need contact the chimp.
		if($this->statusUnpublished()) {
			$this->message($this->_("The chimp can't find bananas here. Please Publish the page."));
			// return the prepare markup
			$this->ChimpStatus = 'page-unpublished';
			return $this->markupPrepare();
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

		$opens = $value->opens == 1 ? 1 : null;
		$html_clicks = $value->html_clicks == 1 ? 1 : null;
		$text_clicks = $value->text_clicks == 1 ? 1 : null;

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
		$opts['generate_text'] =  1;

		$content = array(
			'url'=> $this->frontpage->httpUrl,
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

		$campaign_id = $api->campaignCreate($type, $opts, $content);

		// what is going on ?
		if ($api->errorCode) {
			// show them rotten bananas
			$this->ChimpStatus = 'incomplete-fields';
			$out = $this->markupPrepare();
			// and let the monkey tell us what has happened.
			$this->chimpsError($api->errorCode, $api->errorMessage);
			return $out;
		}

		$value->id = $campaign_id;
		$this->set($this->name, $campaign_id);
		
		$this->ChimpStatus = 'campaign-created';

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
			$this->ChimpStatus = 'campaign-offline';
			return $this->markupCampaign();
		}

		// provide easy access
		$value = $this->get('value');
		$name = $this->get('name');

		// The state of the checkbox is always unchecked, unless you check the box or
		// when the fields are changed. Then jQuery will check the box.
		if ($value->update_settings == false ) {
			$this->ChimpStatus = 'campaign-saved';
			return $this->markupCampaign();
		}

		// wake-up the monkey
		$api = new MCAPI($this->apiKey);

		// update settings
		$api->campaignUpdate( $value->id, 'title', $value->campaign_title);
		$api->campaignUpdate( $value->id, 'subject', $value->subject);
		$api->campaignUpdate( $value->id, 'from_email', $value->from_email);
		$api->campaignUpdate( $value->id, 'from_name', $value->from_name);
		$api->campaignUpdate( $value->id, 'list_id', $value->list);
		$api->campaignUpdate( $value->id, 'generate_text', 1);

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

		$update = $api->campaignUpdate($value->id, 'tracking', $options);

		// Oops, something is wrong. Let the monkey speak to us.
		if ($api->errorCode) {
			// set status & show form
			$this->ChimpStatus = 'campaign-not-found';
			$out = $this->markupCampaign();
			// Let the monkey tell us what is wrong.
			if( $api->errorCode == 300 ) {
				// $out .= "<input type='hidden' name='list' value='' />\n";
				$out .= "<input type='hidden' name='{$name}' value='' />\n";
			}
			$this->chimpsError($api->errorCode, $api->errorMessage);
			return $out;
		}
		
		// set status & show form
		$this->ChimpStatus = 'campaign-updated';
		$out = $this->markupCampaign();
		$this->message($this->_("The settings are updated on MailChimp."));
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

		// reference the this field
		$name = $this->get('name');
		$value = $this->get('value');
		
		// row 1
		$out = "<div class='chimp-row chimp cf'>";

		if(!$this->ChimpStatus) {
			$this->error("markupPrepare methode inside {$this->className} did not receive a ChimpStatus.");
		}
		
		// not all fields are provided
		if($this->ChimpStatus == 'incomplete-fields') {
			$header = "<h2>" . $this->_("Don't be shy.") . "</h2>";
			$content = "<p>" . $this->_('The chimp is waiting for data. Please fill in all fields.').
			$img = "";
		}		
		
		// if page is unpublished
		if($this->ChimpStatus == 'page-unpublished') {
			$header = "<h2>" . $this->_("The page is not visible for the chimp") . "</h2>";
			$content = "<p>" . $this->_('Please fill out the form below and click publish.').
			$img = "";
		}

		// prepare a campaign
		if($this->ChimpStatus == 'prepare') {
			$header = "<h2>" . $this->_("Let's setup a MailChimp campaign.") . "</h2>";
			$content = "<p>" . $this->_('Please fill in the fields & save the page. ').
			$this->_('If everything is chimpy,') . "<br />" .
			$this->_('we receive a campaign id and we\'re good to go.') . "</p>";
			$img = "<img src='" . wire('config')->urls->siteModules . "Inputfield" . $this->className . "/images/freddy.png' />";
		}

 		// column 1 & 2
		$out .= "<div class='chimp-column double {$this->ChimpStatus}'>".
		"		<div class='chimp-padding'>".
		"			<div class='ui-widget ui-widget-content chimp-detail cf'>".
		"				<div class='image'>".
							$img.
		"				</div>".
		" 				<div class='text'>".
							$header.
							$content.
		"				</div>".
		"			</div>".
		"		</div>".
		"	</div>";

		// column 3
		$opens = $this->_("MailChimp adds a tiny image to the mail that can get tracked.");
		$html_clicks = $this->_("Required on free accounts, optional on paid account.");
		$text_clicks = $this->_("Required on free accounts, optional on paid account.");

		$detail = array(
			'opens' => "<span class='icon-detail'><span>every time someone opens the HTML email</span><a href='#' alt='{$opens}' class='chimp-tip'><i class='ui-icon ui-icon-info'>info</i></a></span>",
			'html_clicks' => "<span class='icon-detail'><span>on/off only on paid accounts</span><a href='#' alt='{$html_clicks}' class='chimp-tip'><i class='ui-icon ui-icon-info'>info</i></a></span>",
			'text_clicks' => "<span class='icon-detail'><span>on/off only on paid accounts</span><a href='#' alt='{$text_clicks}' class='chimp-tip'><i class='ui-icon ui-icon-info'>info</i></a></span>",
			);

		$out .= "<div class='chimp-column'>".
		"	<div class='chimp-padding'>".
		"		<div class='ui-widget ui-widget-content chimp-detail'>".
		"			<label class='ui-widget-header'>" . $this->_('Tracking options') . "</label>".
					$detail['opens'] . $this->markupCheckbox('opens', $this->_("Track Opens")).
					$detail['html_clicks'] . $this->markupCheckbox('html_clicks', $this->_('Track Clicks, html')).
					$detail['text_clicks'] . $this->markupCheckbox('text_clicks', $this->_('Track Clicks, plain-text')).
		"		</div>".
		"	</div>".
		"</div>";

		$out .= "</div>"; // .chimp-row

		$title = empty($value->campaign_title) ? $this->frontpage->title : $value->campaign_title;

		// Columns output
		$out .= "<div class='chimp-row chimp cf'>".
			$this->markupInput('campaign_title', $title, $this->_("Name Your Campaign"), $this->_("Only used by MailChimp internal.")).
			$this->markupSelect($this->lists()).
			$this->markupInput('subject', $value->subject, $this->_("Email Subject"), $this->_("The subject line for your campaign message")).
			$this->markupInput('from_name', $value->from_name, $this->_("From"), $this->_("Name. (not email address)")).
			$this->markupInput('from_email', $value->from_email, $this->_("Reply-To"), $this->_("Email address for your campaign message.")).
		"</div>";

		$out .= "<input type='hidden' name='{$name}' value='{$value->id}' />";
		$out .= "<p class='detail'>{$this->_('Fields above are required, but you can change them later if you wish.')}</p>";

		return $out;
	}

	/**
	 * markupInputfield outputs the markup for the campaign inputfield
	 *
	 * @return string markup for created & update campaign
	 */
	public function markupCampaign() {

		// reference the this field
		$name = $this->name;
		$value = $this->value;

		$out = '';

		// row 1
		$out = "<div class='chimp-row chimp cf'>";

		if(!$this->ChimpStatus) {
 			$this->error("markupCampaign methode inside {$this->className} did not receive a ChimpStatus.");
			$header = "<h2>" . "NO STATUS" . "</h2>";
			$img = '';
			$content = 'No Status';			
		}

		// campaign offline
		if($this->ChimpStatus == 'campaign-created') {
			$header = "<h2>" . $this->_("Your campaign is created on MailChimp") . "</h2>";
			$img = "<img src='" . wire('config')->urls->siteModules . "Inputfield" . $this->className . "/images/freddy-updated.png' />";
			$content = "<p>" . $this->_("The HTML is pulled in live from the url of this page. The plain-text version however, needs to be send from here. So you need to check the \"Update on MailChimp\" checkbox and press save if you want to update the plain-text version of this campaign.") . "</p>";
		}
		
		// campaign offline
		if($this->ChimpStatus == 'campaign-offline') {
			$header = "<h2>" . $this->_("Your page is not published") . "</h2>";
			$img = "";
			$content = "<p>" . $this->_("You invited the chimp, but the door is closed. Please publish the page.") . "</p>";
		}
 
		// campaign saved
		if($this->ChimpStatus == 'campaign-saved') {
			$header = "<h2>" . $this->_("We didn't wake up the chimp") . "</h2>";
			$img = "<img src='" . wire('config')->urls->siteModules . "Inputfield" . $this->className . "/images/freddy-saved.png' />";
			$content = "<p>" . $this->_("Your campaign settings are saved in ProcessWire. But the settings are not updated on MailChimp.") . "</p>";
		}

		// campaign updated
		if($this->ChimpStatus == 'campaign-updated') {
			$header = "<h2>" . $this->_("Updated on MailChimp") . "</h2>";
			$img = "<img src='" . wire('config')->urls->siteModules . "Inputfield" . $this->className . "/images/freddy-updated.png' />";
			$content = "<p>" . $this->_("Your campaign is successfully updated on MailChimp.") . "</p>";
		}

		// campaign not found
		if($this->ChimpStatus == 'campaign-not-found') {
			$header = "<h2>" . $this->_("Bananas…") . "</h2>";
			$img = "<img src='" . wire('config')->urls->siteModules . "Inputfield" . $this->className . "/images/bananapeel.jpg' />";
			$content = "<p>" . $this->_("Looks like someone deleted this campaign on MailChimp. Please save the page, then we try to re-create this campaign.") . "</p>";
		}

 		// column 1 & 2
		$out .= "<div class='chimp-column double {$this->ChimpStatus}'>".
		"		<div class='chimp-padding'>".
		"			<div class='ui-widget ui-widget-content chimp-detail cf'>".
		"				<div class='image'>".
							$img.
		"				</div>".
		" 				<div class='text'>".
							$header.
							$content.
		"				</div>".
		"			</div>".
		"		</div>".
		"	</div>";

		$opens = $this->_("MailChimp adds a tiny image to the mail that can get tracked.");
		$html_clicks = $this->_("Required on free accounts, optional on paid account.");
		$text_clicks = $this->_("Required on free accounts, optional on paid account.");

		$detail = array(
			'opens' => 		 "<span class='icon-detail'><span>every time someone opens the HTML email</span><a href='#' alt='{$opens}' class='chimp-tip'><i class='ui-icon ui-icon-info'>info</i></a></span>",
			'html_clicks' => "<span class='icon-detail'><span>on/off only on paid accounts</span><a href='#' alt='{$html_clicks}' class='chimp-tip'><i class='ui-icon ui-icon-info'>info</i></a></span>",
			'text_clicks' => "<span class='icon-detail'><span>on/off only on paid accounts</span><a href='#' alt='{$text_clicks}' class='chimp-tip'><i class='ui-icon ui-icon-info'>info</i></a></span>",
			);

		// column 3
		$out .= "<div class='chimp-column'>".
		"		<div class='chimp-padding'>".
		"			<div class='ui-widget ui-widget-content chimp-detail'>".
		"				<label class='ui-widget-header'>" . $this->_('Tracking options') . "</label>".
						$detail['opens'] . $this->markupCheckbox('opens', $this->_("Track Opens")).
						$detail['html_clicks'] . $this->markupCheckbox('html_clicks', $this->_('Track Clicks, html')).
						$detail['text_clicks'] . $this->markupCheckbox('text_clicks', $this->_('Track Clicks, plain-text')).
		"			</div>".
		"		</div>".
		"	</div>";

		$out .= "</div>"; // .chimp-row

		// row 2
		$out .= "<div class='chimp-row chimp cf'>".
			$this->markupInput('campaign_title', $value->campaign_title, $this->_("Name Your Campaign"), $this->_("Only used by MailChimp internal.")).
			$this->markupSelect($this->lists()).
			$this->markupInput('subject', $value->subject, $this->_("Email Subject"),  $this->_("The subject line for your campaign message")).
			$this->markupInput('from_name', $value->from_name, $this->_("From"), $this->_("Name. (not email address)")).
			$this->markupInput('from_email', $value->from_email, $this->_("Reply-To"), $this->_("Email address for your campaign message.")).
			$this->markupInput('id', $value->id, $this->_("Campaign id"), $this->_("Can't be changed."), $enabled=false).
		"</div>";

		$out .=	"<input type='hidden' name='{$name}' value='{$value->id}' />\n";

		$out .= "<p class='detail right'>" . $this->_("plain-text only get updated when the campaign is updated to MailChimp") . "</p>";

		// row 3
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
		$name = $this->get('name');
		$modules = wire('modules');
		$wrapper = $modules->get('InputfieldFieldset');
		$field = $fieldName == 'from_email' ? $modules->get('InputfieldEmail') : $modules->get('InputfieldText');
		// visual campaign id field should be always disabled
		if(!$enabled) $field->attr('disabled','disabled');
		// don't give a name fort disabled campaign id field
		$field->name = $enabled === true ? $fieldName : '';
		if($fieldName != 'campaign_title' && $fieldName != 'id' ) $field->attr('required', 'required');
		$field->value = $fieldValue;
		$field->label = $fieldLabel;
		$field->description = $fieldDescription;
		$field->notes = "\$page->{$this->get('name')}->{$fieldName}";
		// markup
		$out = "<div class='chimp-column'>".
		"		<div class='chimp-padding'>".
		 			$wrapper->append($field)->render().
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
		$field->attr('required', 'required');
		foreach($array as $option)	{
			$attr = $value->list == $option['id'] ? array('selected' => 'selected') : null;
			$field->addOption($option['id'], $option['name'], $attr);
		}
		// markup
		$out = "<div class='chimp-column'>".
		"		<div class='chimp-padding'>".
		 			$wrapper->append($field)->render().
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
