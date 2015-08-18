<?php
/**
 * Extends the dr_wiki frondend plugin
 * 
 * @author astehlik
 *
 */
class ux_tx_drwiki_pi1 extends tx_drwiki_pi1 {
	
	protected $subscriptionsTable = 'tx_intdrwikimods_subscriptions';
	
	public $allowedWIKI ='<ref><noinclude><references2col><references><nowiki>';
	
	protected $lastKeyword = NULL;
	
	public function main($content, $conf) {

			//in the parent main function, pi_loadLL is called before $this->conf is set
			//so _LOCAL_LANG overrides are ignored, this is a fix
		$this->conf = $conf;
		$this->pi_loadLL();
		
		$this->initLastKeyword();
		$content = parent::main($content, $conf);
		
		$this->doSubscription();
		$content = $this->cObj->substituteMarker($content, '###ICON_SUBSCRIBE###', $this->getSubscribeLink());
				
		$content = $this->cObj->substituteMarker($content, '###ICON_LAST_KEYWORD###', $this->getLastKeywordLink());
		
		return $content;
	}
	
	protected function clearEditPiVars() {
		
		$this->piVars["cmd"] = "";			
		$this->piVars["body"] = "";
		$this->piVars["author"] = "";
		$this->piVars["date"] = "";
		$this->piVars["wiki"] = "";
   		$this->piVars["previewEdit"] = "";
		$this->piVars["showUid"] = "";
		$this->piVars["latest"] = "";
		$this->piVars["pluginSEARCH"]["sword"] = "";
		$this->piVars["pluginSEARCH"]["submit"] = "";
	}
	
	/**
	 * createView
	 *
	 * Displays a form to create a new page or inserts the data if the user has submitted it
	 *
	 * @param	[string]		$content: Content of the extension output
	 * @param	[array]				$conf: Configuration Array of the extension
	 * @return	[string]		Current page content
	 */
	public function createView($content, $conf)
	{
		// get NameSpacefor variables
		$getNS = preg_match_all( '/(.*)(:)(.*)/e', $this->piVars["keyword"] , $NSmatches );
		$this->currentNameSpace = $NSmatches[1][0];
		// redirect if the wiki is only editable when a user is logged in
		if ( ($this->activateAccessControl) )					   // Access control active?
		{   
			if ( !$GLOBALS["TSFE"]->fe_user->user["uid"] > 0 )		// User is NOT logged in?
			{
				$parameters = array("redirect_url" => $this->pi_linkTP_keepPIvars_url(array("cmd" => "edit", "submit" => ""), 1, 0));
				$link = ($this->pageRedirect) ? $this->pi_linkToPage('Log-In',$this->pageRedirect,'',$parameters) : '';
				
				$content = '<div class="wiki-box-red">'.
			 			$this->cObj->cObjGetSingle($this->conf["sys_Warning"], $this->conf["sys_Warning."]).
			 			$this->pi_getLL("pi_edit_login_warning", "Attention: You need to be logged-in ").
						$link.'<br/><br/></div>';
				return $content;
			 }   
			if (  ($this->allowedGroups == true) && (!$this->inGroup ($this->allowedGroups))	// User is NOT in "Allowed Groups"?
				 OR 
				  ($this->disallowedGroups == true) && ($this->inGroup ($this->disallowedGroups))  ) // User IS in "Disallowed Groups"?
			{
				$parameters = array("redirect_url" => $this->pi_linkTP_keepPIvars_url(array("cmd" => "edit", "submit" => ""), 1, 0));	
				$content = '<div class="wiki-box-red">'.
			 			$this->cObj->cObjGetSingle($this->conf["sys_Warning"], $this->conf["sys_Warning."]).
			 			$this->pi_getLL("pi_edit_disallowed", "Sorry, you are not allowed to edit or create this article. Please talk to the administrator if you think this is an error.").
						'<br/><br/></div>';
				return $content;
			}
		}
		if ($this->piVars["submitCreate"] && !$this->read_only)
		{
			// the user has filled out the form before, so we insert the
			// data in the database and reset the pi-variables. Then we display
			// the current keyword (the page we have just created)
			$this->piVars["body"] = $this->replaceSignature($this->piVars["body"]);
			// exec_INSERTquery is sql-injection safe, no quoting needed here				
			
			if ($this->piVars['summary'] == $this->initSummary) {$this->piVars['summary'] = '';};
			//check if previous record is locked (only when admin user is present)
			$isLocked = 0;
			if ($this->isUserWikiAdmin()) $isLocked = $this->isRecordLocked($this->piVars['keyword']);
			
			// check hiding status --> only set it when email notification is
			// active - otherwise set to false
			if ($this->mailNotify) 
				{$hidden = $this->mailHideItem;}
			else {$hidden = false;}
			
			$pageContent = array(
					'pid' => $this->storagePid,
					'crdate' => time(),
					'tstamp' => time(),
					'keyword' => trim($this->piVars['keyword']),
					'summary' => $this->piVars['summary'],
					'body' => $this->piVars['body'],
					'date' => $this->piVars['date'],
					'author' => $this->piVars['author'],
					'locked' => $isLocked,
					'hidden' => $hidden,
				);
			
			$res = $GLOBALS['TYPO3_DB']->exec_INSERTquery(
				'tx_drwiki_pages',
				 $pageContent
			);
			
			$this->clearEditPiVars(); 
			$this->piVars["section"] = "";
			$this->piVars["referer"] = "";
			 
			// send mail and add note to the output that everything was saved 
			$note ="";			
			if ($this->mailNotify) {
				$this->mailAdmin($GLOBALS['TYPO3_DB']->sql_insert_id(), $pageContent['keyword'], $pageContent['body']);
				$note = '<div class="wiki-box-yellow">'.
				 	$this->cObj->cObjGetSingle($this->conf["sys_Info"], $this->conf["sys_Info."]).
				 	$this->pi_getLL("pi_mail_notify_warning", "Your changes were mailed to the wiki editor and will be added later on.").
					'<br/><br/></div>';					
			}

			return $note . $this->singleView($content, $conf);
		} else {
			// the user has freshly clicked a create-link, so we display
			// the form
			
			//create captcha
			//$captcha = $this->freeCap->makeCaptcha();
			
			// get user name
			$author = $this->getUser();
			
			$markerArray = $this->getEditMarkerArray('', $author, '', 'add');
			$content = $this->cObj->getSubpart($this->templateCode, "###EDIT_FROM###");
			$content = $this->cObj->substituteMarkerArray($content, $markerArray);
			$content = $this->cObj->substituteSubpart($content, '###EDIT_FROM_PREVIEW###', '');
			$content = $this->cObj->substituteSubpart($content, '###PREVIEW_BUTTON###', '');
			
			$this->piVars["referer"] = "";
			return $content;
		}
	}
	
	protected function doSubscription() {
		
		if (!$this->piVars['subscribe']) {
			return;
		}
		
		$user = tx_intdiv_fe::getCurrentFeUserIfLoggedIn();
		$sqlUserID = intval($user->user['uid']);
		
		if ($this->piVars['subscribe']=='subscribe') {
			$GLOBALS['TYPO3_DB']->exec_INSERTquery(
				$this->subscriptionsTable,
				array(
					'keyword'=>$this->getSqlKeyword(),
					'fe_user'=>$sqlUserID
				),
				array('keyword'));
		}
		else if ($this->piVars['subscribe']=='unsubscribe') {
			$GLOBALS['TYPO3_DB']->exec_DELETEquery($this->subscriptionsTable, 'keyword=' . $this->getSqlKeyword() . ' AND fe_user=' . $sqlUserID);
		}
		
		$url = t3lib_div::locationHeaderUrl($this->pi_linkTP_keepPIvars_url(array('subscribe'=>'')));
		header('Location: ' . $url);
		die('You are redirected to ' . $url);
	}
	
	# parameter level defines if we are on an indentation level
	public function doTocLine( $anchor, $tocline, $level ) {
				//if a base url is set, anchor links will only work, if the
				//full request is prepended
			$url = t3lib_div::getIndpEnv('REQUEST_URI');
			$link = '<a href="' . $url . '#'.$anchor . '">' . $tocline . '</a><br />';
			if($level) {
					return $link."\n";
			} else {
					return '<div class="tocline">'.$link."</div>\n";
			}
	}
	
	/**
	 * editView
	 *
	 * Displays a form to edit a  page (create a new version) or inserts the data if the user has
	 *
	 * @param	[string]		$content: Content of the extension output
	 * @param	[array]		$conf: Configuration Array of the extension
	 * @return	[string]		Current page content
	 */
	public function editView($content, $conf)
	{
		// TODO: Make Form part of the Template....
		// get the latest ID to check for newer versions...
		$latestUID = $this->getUid($this->piVars["keyword"]);
		
		// get NameSpacefor variables
		$getNS = preg_match_all( '/(.*)(:)(.*)/e', $this->piVars["keyword"] , $NSmatches );
		$this->currentNameSpace = $NSmatches[1][0];

		if ($this->piVars["submitEdit"] && !$this->read_only)
		{		
			// the user has filled out the form before and submitted it, so we insert the
			// given data in the database and display the given keyword (this displays the freshly
			// created version)
					
			// check if a newer version is available or not:
			// No newer version:
			if ($latestUID <= $this->piVars["latest"]) {			
				
				//reassemble sections
				if ($this->piVars['section']) {
					//Get Data and Versions...
					$latestUid = $this->getUid($this->piVars["keyword"]);
					$latestVersion = $this->pi_getRecord("tx_drwiki_pages", $latestUid, 1);
					$this->piVars['body'] = $this->replaceSection($this->piVars['section'],$this->piVars['body'],$latestVersion['body']);
				}
				
				$this->piVars["body"] = $this->replaceSignature($this->piVars["body"]);
				
				// remove summary text if it has not been changed
				if ($this->piVars['summary'] == $this->initSummary) { 
					$this->piVars['summary'] = '';
				}
				
				//check if previous record is locked (only when admin user is present)
				$isLocked = 0;
				if ($this->isUserWikiAdmin()) {
					$isLocked = $this->isRecordLocked($this->piVars['keyword']);
				}
				
				// check hiding status --> only set it when email notification is
				// active - otherwise set to false
				if ($this->mailNotify) {
					$hidden = $this->mailHideItem;
				}
				else {
					$hidden = false;
				}
					
				$pageContent = array(
						'pid' => $this->storagePid,
						'crdate' => time(),
						'tstamp' => time(),
						'summary' => $this->piVars['summary'],
						'keyword' => trim($this->piVars['keyword']),
						'body' => $this->piVars['body'],
						'date' => $this->piVars['date'],
						'author' => $this->piVars['author'],
						'locked' => $isLocked,
						'hidden' => $hidden,
					);
					
				//TODO: Check if unset aray could be done more efficint
				$this->clearEditPiVars();
				$this->piVars["section"] = "";
				$this->piVars["submitEdit"] = "";
				$this->piVars["summary"] = "";
				
				// HOOK: insert only if hook returns OK or is not set
				if($this->hook_submit_beforeInsert($pageContent)){
					$res = $GLOBALS['TYPO3_DB']->exec_INSERTquery(
					'tx_drwiki_pages',
					$pageContent
					);
					
					$note = '';
					if ($this->mailNotify) {
						$this->mailAdmin($GLOBALS['TYPO3_DB']->sql_insert_id(), $pageContent['keyword'], $pageContent['body']);
							$note = '<div class="wiki-box-yellow">'.
							 	$this->cObj->cObjGetSingle($this->conf["sys_Info"], $this->conf["sys_Info."]).
							 	$this->pi_getLL("pi_mail_notify_warning", "Your changes were mailed to the editor and will be added later on.").
									'<br/><br/></div>';		 					
					}				
				}
				// HOOK: to do something after insert
				$this->hook_submit_afterInsert($pageContent);
	
				return $note . $this->singleView($content, $conf);
			} else {  // changes are detected....
				$this->clearEditPiVars();
				$this->piVars["section"] = "";
				$this->piVars["submitEdit"] = "";
				$this->piVars["summary"] = "";
	
				//get latest version of wiki-page from DB			
				$this->internal["currentRow"] = $this->pi_getRecord("tx_drwiki_pages", $latestUID, 1);
				return '<div class="wiki-box-red">'.$this->cObj->cObjGetSingle($this->conf["sys_Warning"], $this->conf["sys_Warning."]).$this->pi_getLL("pi_edit_ver_warning", "Attention: newer version detected!").'</div>' . $this->singleView($this->internal["currentRow"]["content"], $conf);
			}		
		}
		elseif ($this->piVars["previewEdit"] && !$this->read_only)
		{
			$tmp_summary = $this->piVars["summary"];
			$this->piVars["summary"] = "";
			$tmp_body = $this->piVars["body"];
			
			//get Preview
			$previewContent = $this->parse($tmp_body,1);
			$tmp_author = $this->piVars["author"];
			$this->clearEditPiVars();
			
			$markerArray = $this->getEditMarkerArray($tmp_body, $tmp_author, $tmp_summary);
			
			$previewMarker = array(
				'###LABEL_PREVIEW###' => $this->pi_getLL("pi_edit_preview", "Preview"),
				'###PREVIEW_CONTENT###' => $previewContent,
			);
			$content = $this->cObj->getSubpart($this->templateCode, "###EDIT_FROM###");
			
			$preview = $this->cObj->getSubpart($content, "###EDIT_FROM_PREVIEW###");
			$preview = $this->cObj->substituteMarkerArrayCached($preview, $previewMarker);
			
			$content = $this->cObj->substituteMarkerArray($content, $markerArray);
			$content = $this->cObj->substituteSubpart($content, '###EDIT_FROM_PREVIEW###', $preview);
			return $content;
		}
		else
		{
			if ( ($this->activateAccessControl) )			   // Access control active?
			{   
				if ( !$GLOBALS["TSFE"]->fe_user->user["uid"] > 0 )	// User is NOT logged in?
				{
					$parameters = array("redirect_url" => $this->pi_linkTP_keepPIvars_url(array("cmd" => "edit", "submit" => ""), 1, 0));
					$link = ($this->pageRedirect) ? $this->pi_linkToPage('Log-In',$this->pageRedirect,'',$parameters) : '';
					
					$content = '<div class="wiki-box-red">'.
					 			$this->cObj->cObjGetSingle($this->conf["sys_Warning"], $this->conf["sys_Warning."]).
					 			$this->pi_getLL("pi_edit_login_warning", "Attention: You need to be logged-in ").
										$link.'<br/><br/></div>';
					return $content;
				}   
				if (  ($this->allowedGroups == true) && (!$this->inGroup ($this->allowedGroups))	// User is NOT in "Allowed Groups"?
					 OR 
					($this->disallowedGroups == true) && ($this->inGroup ($this->disallowedGroups))  ) // User IS in "Disallowed Groups"?
				{
					$parameters = array("redirect_url" => $this->pi_linkTP_keepPIvars_url(array("cmd" => "edit", "submit" => ""), 1, 0));	
					$content = '<div class="wiki-box-red">'.
					 			$this->cObj->cObjGetSingle($this->conf["sys_Warning"], $this->conf["sys_Warning."]).
					 			$this->pi_getLL("pi_edit_disallowed", "Sorry, you are not allowed to edit or create this article. Please talk to the administrator if you think this is an error.").
										'<br/><br/></div>';
					return $content;
				}
			}

			// display the edit form
			// TODO: take layout from template
	
			$author = $this->getUser();
			
			// if we don't know the uid, we search it by the keyword
			if (!$this->piVars["showUid"])
			{
			$this->piVars["showUid"] = $this->getUid($this->piVars["keyword"]);
			}
	
			// get the record
			$this->internal["currentTable"] = "tx_drwiki_pages";
			$this->internal["currentRow"] = $this->pi_getRecord("tx_drwiki_pages", $this->piVars["showUid"], 1);
			
			
			//get the section to be edited
			if($this->piVars["section"]) {
				$this->internal["currentRow"]['body'] = $this->getSection($this->getFieldContent("body"), $this->piVars["section"]);
			}
	
			// if we have no keyword we take it from the current data
			if (!$this->piVars["keyword"])
			{
			$this->piVars["keyword"] = trim($this->getFieldContent("keyword"));
			}
			
			$markerArray = $this->getEditMarkerArray($this->getFieldContent("body"), $author);
			$content = $this->cObj->getSubpart($this->templateCode, "###EDIT_FROM###");
			$content = $this->cObj->substituteMarkerArray($content, $markerArray);
			$content = $this->cObj->substituteSubpart($content, '###EDIT_FROM_PREVIEW###', '');

			return $content;
		}
	}
	
	/**
	 * Returns a link for editing a section, if the linkText parameter ist empty
	 * the text will be read from _LOCAL_LANG
	 * 
	 * In _LOCAL_LANG the key "pi_edit_sectionedit" must exist
	 * 
	 * @param int $section numer of the section
	 * @param string $linkText text that will be linkend (will get wrapped in [ ])
	 */
	public function getEditLink ($section, $linkText = NULL) {
		
		if($linkText==NULL) {
			$linkText = $this->pi_getLL('pi_edit_sectionedit');
		}
		
		return parent::getEditLink($section, $linkText);
	}
	
	protected function getEditMarkerArray($body, $author = '', $summary = '', $mode = 'edit') {
		
		$prefill = '';
		if ($this->activateInitialPageText) {
			$prefill .= "[".$this->pi_getLL("pi_add_template", "Add template:");
			$prefill .= ' <a href="#" onclick="addTemplate(\''.$this->initialPageText1.'\', \''.$this->prefixId.'[body]\'); return false;">'.$this->initialPageText1Name.'</a>';
			$prefill .= ' | <a href="#" onclick="addTemplate(\''.$this->initialPageText2.'\', \''.$this->prefixId.'[body]\'); return false;">'.$this->initialPageText2Name.'</a>';
			$prefill .= ' | <a href="#" onclick="addTemplate(\''.$this->initialPageText3.'\', \''.$this->prefixId.'[body]\'); return false;">'.$this->initialPageText3Name.'</a> ]';
		}
		
		$markerArray = array(
			'###FORM_NAME###' => $mode=='edit' ? 'EditForm' : 'CreateForm',
			'###SUBMIT_SAVE_NAME###' => $mode=='edit' ? 'submitEdit' : 'submitCreate',
			'###LABEL_EDIT_HEADER###' => $this->pi_getLL('pi_' . $mode . '_header'),
			'###VALUE_KEYWORD###' => $this->piVars["keyword"],
			'###VALUE_USER###' => $this->getUser(),
			'###TOOLBAR###' => $this->getEditToolbar(),
			'###PREFILL_CONTENT###	' => $prefill,
			'###FROM_PREFIX###' => $this->prefixId,
			'###FORM_ACTION###' => $this->pi_linkTP_keepPIvars_url($mode=='edit' ? array() : array('cmd' => ''), 1, 0),
			'###LABEL_SUBMIT###' => $this->pi_getLL("pi_edit_save", "Save Page"),
			'###LABEL_PREVIEW###' => $this->pi_getLL("pi_edit_preview", "Preview"),
			'###LABEL_RESET###' => $this->pi_getLL("pi_edit_reset", "Reset"),
			'###LINK_CANCEL###' => $this->pi_linkTP_keepPIvars($this->pi_getLL("pi_edit_cancel", "Cancel / Exit"), array("pluginSEARCH" => "", "submitCreate" =>"", "keyword" => $this->piVars["keyword"], "showUid" => "", "cmd" => ""), 1, 0),
			'###SIZE_BODY_COLS###' => $this->numberColumns,
			'###SIZE_BODY_ROWS###' => $this->numberRows,
			'###WRAP_LINES_ON###' => $this->wrapLinesOn,
			'###VALUE_BODY###' => $body,
			'###LABEL_SUMMARY###' => $this->pi_getLL("listFieldHeader_summary"),
			'###SIZE_SUMMARY###' => $this->charSummary,
			'###VALUE_SUMMARY###' => $summary,
			'###VALUE_DATE###' => date('Y-m-d H:i:s'),
			'###VALUE_AUTHOR###' => $author,
			'###VALUE_SECTION###' => trim($this->piVars["section"]),
			'###VALUE_LATEST_UID###' => $this->getUid($this->piVars["keyword"]),
			'###TOOLS###' => $this->cObj->getSubpart($this->templateCode, "###EDIT_TOOLS###"),
			'###USED_TEMPLATES###' => $this->getUsedTemplateList($body),
			'###CMD###' => $mode=='edit' ? 'edit' : '',
			'###ICON_BACK###' => $this->pi_linkTP_keepPIvars($this->cObj->cObjGetSingle($this->conf["iconBack"], $this->conf["iconBack."]), array("showUid" => "", "cmd" => "", "pointer" => "", "diff_uid" => "", "keyword" => $this->piVars['keyword']), 1, 0),
		);
		
		return $markerArray;
	}
	
		
	/**
	 * toolarray an array of arrays which each include the filename of
	 * the button image (without path), the opening tag, the closing tag,
	 * and optionally a sample text that is inserted between the two when no
	 * selection is highlighted.
	 * The tip text is shown when the user moves the mouse over the button.
	 *
	 * Already here are accesskeys (key), which are not used yet until someone
	 * can figure out a way to make them work in IE. However, we should make
	 * sure these keys are not defined on the edit page.
	 */
	public function getEditToolbar() {
	 
		$toolarray=array(
			array(
				'image'=>'button_bold.png',
				'open'	=>	"\'\'\'",
				'close'	=>	"\'\'\'",
				'sample'=>	$this->pi_getLL("tb_bold_sample", "Bold text"),
				'tip'	=>	$this->pi_getLL("tb_bold_tip", "Bold text"),
				'key'	=>	'B'
			),
			array(	
				'image'=>'button_italic.png',
				'open'	=>	"\'\'",
				'close'	=>	"\'\'",
				'sample'=> $this->pi_getLL("tb_italic_sample", "Italic text"),
				'tip'	=>	$this->pi_getLL("tb_italic_tip", "Italic text"),
				'key'	=>	'I'
			),
			array(
				'image'=>'button_link.png',
				'open'	=>	'[[',
				'close'	=>	']]',
				'sample'=>	$this->pi_getLL("tb_link_sample", "Link title"),
				'tip'	=>	$this->pi_getLL("tb_link_tip", "Internal link"),
				'key'	=>	'L'
			),
			/*
			array(
				'image'=>'button_extlink.png',
				'open'	=>	'[',
				'close'	=>	']',
				'sample'=>	$this->pi_getLL("tb_extlink_sample", "http://www.example.com link title"),
				'tip'	=>	$this->pi_getLL("tb_extlink_tip", "External link (remember http:// prefix)"),
				'key'	=>	'X'
			),*/
			array(
				'image'=>'button_headline.png',
				'open'	=>	"\\n== ",
				'close'	=>	" ==\\n",
				'sample'=>	$this->pi_getLL("tb_headline_sample", "Headline text"),
				'tip'	=>	$this->pi_getLL("tb_headline_tip", "Level 2 headline"),
				'key'	=>	'H'
			),
			array(
				'image'	=>'button_hr.png',
				'open'	=>	"\\n----\\n",
				'close'	=>	'',
				'sample'=>	'',
				'tip'	=>	$this->pi_getLL("tb_hr_tip", "Horizontal line (use sparingly)"),
				'key'	=>	'R'
			),
			array(
				'image'	=>'button_sig.png',
				'open'	=>	"\\n--~~~~\\n",
				'close'	=>	'',
				'sample'=>	'',
				'tip'	=>	$this->pi_getLL("tb_sig_tip", "Signature"),
				'key'	=>	'S'
			),
			array(
				'image'	=>'button_nowiki.png',
				'open'	=>	"<nowiki>",
				'close'	=>	'</nowiki>',
				'sample'=>	$this->pi_getLL("tb_nowiki_sample", "This is not parsed"),
				'tip'	=>	$this->pi_getLL("tb_nowiki_tip", "Nowiki"),
				'key'	=>	'N'
			),
			array(
				'image'	=>'button_sub.png',
				'open'	=>	"<sub>",
				'close'	=>	'</sub>',
				'sample'=>	'',
				'tip'	=>	$this->pi_getLL("tb_sub_tip", "Sub"),
				'key'	=>	'D'
			),
			array(
				'image'	=>'button_sup.png',
				'open'	=>	"<sup>",
				'close'	=>	'</sup>',
				'sample'=>	'',
				'tip'	=>	$this->pi_getLL("tb_sup_tip", "Sup"),
				'key'	=>	'U'
			),
			array(
				'image'	=>'button_strike.png',
				'open'	=>	"<s>",
				'close'	=>	'</s>',
				'sample'=>	$this->pi_getLL("tb_strike_sample", "This text is strike through"),
				'tip'	=>	$this->pi_getLL("tb_strike_tip", "Strike Through"),
				'key'	=>	''
			),
			array(
				'image'	=>'button_ref.png',
				'open'	=>	"<ref>",
				'close'	=>	'</ref>',
				'sample'=>	$this->pi_getLL("tb_ref_sample", "This is a reference"),
				'tip'	=>	$this->pi_getLL("tb_ref_tip", "Add reference"),
				'key'	=>	'R'
			),
		);
		$toolbar ="<script type='text/javascript'>\n/*<![CDATA[*/\n";
	
		$toolbar.="document.writeln(\"<div id='toolbar'>\");\n";
		
		foreach($toolarray as $tool) {
			$image = substr(t3lib_div::getFileAbsFileName($this->conf['toolbarIconPath']), strlen(PATH_site)) . $tool['image'];
			$open = $tool['open'];
			$close = $tool['close'];
			$sample = $tool['sample'];
			$tip = $tool['tip'];
			$key = $tool["key"]; // accesskey for the buttons
		
			$toolbar.="addButton('$image','$tip','$open','$close','$sample','$key');\n";
		}
	
		$toolbar.="addInfobox('".$this->pi_getLL("tb_infobox")."','".$this->pi_getLL("tb_infobox_alert")."');\n";
		$toolbar.="document.writeln(\"</div>\");\n";
	
		$toolbar.="/*]]>*/\n</script>";
		
		// Add Scripts
		$JS_Param = "var drWikiEditor='".$this->prefixId."[body]'; //Handler for Editor\n";
		$this->loadExtJS("res/wiki_script.js", $JS_Param);
		
		return $toolbar;		
	}
	
	protected function getLastKeywordLink() {
		if($this->lastKeyword === NULL) {
			return '';
		}
		else {
			return $this->pi_linkTP_keepPIvars($this->cObj->cObjGetSingle($this->conf['iconBack'], $this->conf['iconBack.']), array('showUid' => '', 'cmd' => '', 'pointer' => '', 'diff_uid' => '', 'keyword' => $this->lastKeyword, 'isBackLink' => 1), 1, 0);
		}
	}
	
	protected function getSqlKeyword() {
		$sqlkeyword = $GLOBALS['TYPO3_DB']->fullQuoteStr(trim($this->piVars['keyword']),'tx_intdrwikimods_abos');
		if($this->piVars['keyword'] == '') {
			$sqlkeyword = $this->wikiHomePage;
		}
		return $sqlkeyword;
	}
	
	protected function getSubscribeLink() {
		
		$user = tx_intdiv_fe::getCurrentFeUserIfLoggedIn();
		$userID = $user->user['uid'];
		
		$sqlkeyword = $this->getSqlKeyword();
		
		$hasAbo = FALSE;
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('fe_user', $this->subscriptionsTable, 'fe_user=' . intval($userID) . ' AND keyword=' . $sqlkeyword);
		if($GLOBALS['TYPO3_DB']->sql_num_rows($res)) {
			$hasAbo = TRUE;	
		}		
		
		if ($hasAbo) {
			$link = $this->makeIconLink(
				$this->cObj->cObjGetSingle($this->conf["iconUnsubscribe"], $this->conf["iconUnsubscribe."]),
				$this->pi_linkTP_keepPIvars_url(array('subscribe'=>'unsubscribe'))
			); 
		}
		else {
			$link = $this->makeIconLink(
				$this->cObj->cObjGetSingle($this->conf["iconSubscribe"], $this->conf["iconSubscribe."]),
				$this->pi_linkTP_keepPIvars_url(array('subscribe'=>'subscribe'))
			);
		}
		
		return $link;
	}
	
	protected function initlastKeyword() {
		
		$currentArray = $GLOBALS['TSFE']->fe_user->getKey('ses', 'tx_drwiki_keywords');
		if(!is_array($currentArray)) {
			$currentArray = array();
		}
		
		$lastKeyword = NULL;
		$keyword = $this->piVars['keyword'];
		if($keyword === NULL) {
			$keyword = $this->wikiHomePage;
		}
		
		if(!intval($this->piVars['isBackLink'])) {
			
			if(count($currentArray)) {
				
				if($keyword == end($currentArray)) {
					if(count($currentArray)>1) {
						$lastKeyword = prev($currentArray);
					}	
				}
				else {
					$lastKeyword = end($currentArray);
				}
				
			}
			
			if($keyword!==end($currentArray)) {
				array_push($currentArray, $keyword);	
			}
			
		}
		else {
			
			if(count($currentArray)>1) {
				array_pop($currentArray);
			}
			
			if(count($currentArray)>1) {
				end($currentArray);
				$lastKeyword = prev($currentArray);
			}
		}
		
		$this->piVars['isBackLink'] = '';
		
		if($lastKeyword !== $keyword) {
			$this->lastKeyword = $lastKeyword;
		}

		$GLOBALS['TSFE']->fe_user->setKey('ses', 'tx_drwiki_keywords', $currentArray);
	}
	
	/**
	 * versionListView
	 *
	 * returns a version listview for the (url-)given keyword
	 * uses the template layout (subpart VERSION_LIST)
	 *
	 * @param	[string]		$content: Content of the extension output
	 * @param	[array]				$conf: Configuration Array of the extension
	 * @return	[string]		Listview of the current wiki-page (keyword)
	 */
	public function versionListView($content, $conf)
	{
		$markerArray = array();
	
		if ($this->piVars["diff_uid"]) {
		
			 // parse and replace subparts in the template file
			$subpart = $this->cObj->getSubpart($this->templateCode, "###DIFF_VIEW###");
		   // globale replacing
			$markerArray["###KEYWORD###"] = '[['.$this->piVars["keyword"].']]'; 
			// Replace markers in template
			$markerArray["###ICON_VERSIONS###"] = $this->makeIconLink(
								$this->cObj->cObjGetSingle($this->conf["iconVersions"], $this->conf["iconVersions."]),
								$this->pi_linkTP_keepPIvars_url(array("cmd" => "list", "showUid" => "", "diff_uid" => "", "keyword" => $this->piVars["keyword"]), 1, 0)
								);
			$markerArray["###ICON_BACK###"] = $this->pi_linkTP_keepPIvars($this->cObj->cObjGetSingle($this->conf["iconBack"], $this->conf["iconBack."]), array("showUid" => "", "cmd" => "", "pointer" => "", "diff_uid" => "", "keyword" => $this->piVars["keyword"]), 1, 0);
			$markerArray["###ICON_HOME###"] = $this->pi_linkTP_keepPIvars($this->cObj->cObjGetSingle($this->conf["iconHome"], $this->conf["iconHome."]), array("showUid" => "", "cmd" => "","pointer" => "", "diff_uid" => "", "keyword" => $this->wikiHomePage), 1, 0);
			
			//Get Data and Versions...
			$latestUid = $this->getUid($this->piVars["keyword"]);
			$newestVersion = $this->pi_getRecord("tx_drwiki_pages", $latestUid, 1);
			$olderVersion = $this->pi_getRecord("tx_drwiki_pages", $this->piVars["diff_uid"], 1);
			

			$markerArray["###DIFF_LATEST_UID###"] =$latestUid;
			$markerArray["###DIFF_DIFF_UID###"] =$this->piVars["diff_uid"];
			
			if (strcmp($olderVersion["body"], $newestVersion["body"])) {
			
				//load diff engineand do the diff
				require_once(PATH_t3lib.'class.t3lib_diff.php');
				$diffEngine = t3lib_div::makeInstance('t3lib_diff');
				
				// do diff word by word (red vs. green)
				$result = $diffEngine->makeDiffDisplay($olderVersion["body"],$newestVersion["body"]);
				$result = nl2br($result);
				$markerArray["###DIFF_RESULT###"] = '<p class="diff-result">'.$result.'</p>';
			
			} else {
				$markerArray["###DIFF_RESULT###"] ='<p><strong>The two test strings are exactly the same!</strong></p>';
			}
			
			//format strings
			$newestVersion = preg_replace('|\r\n|', '<br />', $newestVersion);
			$olderVersion = preg_replace('|\r\n|', '<br />', $olderVersion);
			
			$markerArray["###DIFF_RESULT###"] .= '<table class="diff-table">'.
					 '<tr><td class="diff-r" style="font-weight:bold">'.$this->piVars["keyword"].' (ID: '.$this->piVars["diff_uid"].') '.$olderVersion["date"].' by '.$olderVersion["author"].' &rArr </td>'.
					 '<td class="diff-g" style="font-weight:bold">'.$this->piVars["keyword"].' (ID: '.$latestUid.') '.$newestVersion["date"]. ' by '.$newestVersion["author"].' &rArr;</td></tr>'.
					 '<tr><td class="diff-table-cell-red">'.$olderVersion["body"].'</td>'.
					 '<td class="diff-table-cell-green">'.$newestVersion["body"] .'</td></tr>'.
					 '</table>';
			
			$subpart = $this->cObj->substituteMarkerArrayCached($subpart, $markerArray);
			
		} else {
			$pidList = $this->pi_getPidList($this->conf["pidList"], $this->conf["recursive"]);
			
			// get it into FlexForms!!!
			list($this->internal["orderBy"], $this->internal["descFlag"]) = explode(":", $this->piVars["sort"]);
			$this->internal["results_at_a_time"] = t3lib_div::intInRange($this->conf["listView."]["results_at_a_time"], 0, 1000, 3);
			// Number of results to show in a listing.
			$this->internal["maxPages"] = t3lib_div::intInRange($this->conf["listView."]["maxPages"], 0, 1000, 2);
			// The maximum number of "pages" in the browse-box: "Page 1", "Page 2", etc.
			$this->internal["orderByList"] = "uid,author,tstamp";

			$sqlkeyword = $GLOBALS['TYPO3_DB']->fullQuoteStr(trim($this->piVars['keyword']),'tx_drwiki_pages');
			$where = 'tx_drwiki_pages.pid IN ('.$pidList.')'.$this->cObj->enableFields('tx_drwiki_pages').' AND keyword = '.$sqlkeyword;
			// Get number of existing versions of this wikipage
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery (
				'COUNT(*)',
				'tx_drwiki_pages',
				$where
			);
			list($this->internal['res_count']) = $GLOBALS['TYPO3_DB']->sql_fetch_row($res);
			
			// validate to positive ints
			$results_at_a_time = t3lib_div::intInRange($this->internal["results_at_a_time"], 1, 1000);
			$pointer = t3lib_div::intInRange($this->piVars['pointer'],0,1000);
			
			// sorting of shown records
			if (t3lib_div::inList($this->internal["orderByList"], $this->internal["orderBy"])) {
				$orderby = $GLOBALS['TYPO3_DB']->fullQuoteStr($this->internal['orderBy'].($this->internal['descFlag']?' DESC':''),'tx_drwiki_pages');
			} else {
				$orderby = 'uid DESC';
			}
			// limits for pageview
			$limit = $pointer * $results_at_a_time.','.$results_at_a_time;
			
			// Get the records to show on this page
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery ('*','tx_drwiki_pages',$where,'',$orderby,$limit);

			// parse and replace subparts in the template file
			$subpart = $this->cObj->getSubpart($this->templateCode, "###VERSION_LIST###");			
			$subpartHeader = $this->cObj->getSubpart($subpart, "###VERSION_LIST_HEADER");
			$subpartRows = $this->cObj->getSubpart($subpart, "###VERSION_LIST_ROWS");
			$templateRow = $this->cObj->getSubpart($subpartRows, "###VERSION_LIST_ROW");
			$templateRowOdd = $this->cObj->getSubpart($subpartRows, "###VERSION_LIST_ROW_ODD");


			// globale replacing
			$markerArray["###KEYWORD###"] = $this->piVars["keyword"];
			
			// Replace markers in template
			$markerArray["###BROWSER###"] = $this->pi_list_browseresults();
			$markerArray["###ICON_BACK###"] = $this->pi_linkTP_keepPIvars($this->cObj->cObjGetSingle($this->conf["iconBack"], $this->conf["iconBack."]), array("showUid" => "", "cmd" => "","pointer" => "", "diff_uid" => "", "keyword" => $this->piVars["keyword"]), 1, 0);
			$markerArray["###ICON_HOME###"] = $this->pi_linkTP_keepPIvars($this->cObj->cObjGetSingle($this->conf["iconHome"], $this->conf["iconHome."]), array("showUid" => "", "cmd" => "","pointer" => "", "diff_uid" => "", "keyword" => $this->wikiHomePage), 1, 0);

			$subpart = $this->cObj->substituteMarkerArrayCached($subpart, $markerArray);

			// Header
			$markerArray["###HEADER_UID###"] = $this->getFieldHeader_sortLink("uid");
			$markerArray["###HEADER_AUTHOR###"] = $this->getFieldHeader_sortLink("author");
			$markerArray["###HEADER_TSTAMP###"] = $this->getFieldHeader_sortLink("tstamp");
			$markerArray["###HEADER_SUMMARY###"] = $this->getFieldHeader("summary");
			$markerArray["###HEADER_DIFF_SELECT###"] = $this->getFieldHeader("diff");

			$subpartHeader = $this->cObj->substituteMarkerArrayCached($subpartHeader, $markerArray);
			$subpart = $this->cObj->substituteSubpart($subpart, "###VERSION_LIST_HEADER###", $subpartHeader);

			// Columns
			$tmpRow = $this->internal["currentRow"];
			$c = 0;
			$subpartRows = "";
			while ($this->internal["currentRow"] = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))
			{
				$markerArray["###UID###"] = $this->getFieldContent("uid");
				if ($this->getFieldContent("author")) {$markerArray["###AUTHOR###"] = $this->getFieldContent("author");}
					else {$markerArray["###AUTHOR###"] = $this->anonymousUser;}
				$markerArray["###TSTAMP###"] = $this->getFieldContent("tstamp");
				$markerArray["###DIFF_SELECT###"] = $this->getFieldContent("diff");
				$markerArray["###SUMMARY###"] = strip_tags($this->getFieldContent("summary"));
				$markerArray["###EDIT_PANEL###"] = $this->pi_getEditPanel($this->internal["current_row"]);

				if (!($c%2))
				{
					$subpartRows .= $this->cObj->substituteMarkerArrayCached($templateRow, $markerArray);
				}
				else
					{
					$subpartRows .= $this->cObj->substituteMarkerArrayCached($templateRowOdd, $markerArray);
				}
				$c++;
			}

			$subpart = $this->cObj->substituteSubpart($subpart, "###VERSION_LIST_ROWS###", $subpartRows);

			$this->internal["currentRow"] = $tmpRow;
		}
		return $subpart;
	}
}
?>