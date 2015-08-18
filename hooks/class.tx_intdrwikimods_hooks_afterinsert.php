<?php

class tx_intdrwikimods_hooks_afterinsert {

	function drwiki_SubmitAfterInsert($pageContent) {
		
		$keyword = $GLOBALS['TYPO3_DB']->fullQuoteStr(trim($pageContent['keyword']), 'tx_intdrwikimods_subscriptions');
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tx_intdrwikimods_subscriptions,fe_users', 'fe_users.deleted=0 AND fe_users.disable=0 AND fe_users.uid=tx_intdrwikimods_subscriptions.fe_user AND tx_intdrwikimods_subscriptions.keyword=' . $keyword);		
		
		if(!$GLOBALS['TYPO3_DB']->sql_num_rows($res)) {
			return;
		}
		
		while($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			
			$mail = t3lib_div::makeInstance('t3lib_htmlmail');
			$mail->recipient = '"' . $row['name'] . '" <' . $row['email'] . '>';
			$mail->subject = '[Serviceportal Wiki] Änderung an:' . $pageContent['keyword']; 
			
			$mail->from_email = 'wiki@serviceportal.intera.de';
			$mail->from_name = 'Serviceportal Wiki';
			$mail->charset = 'utf-8';
			$mail->mailer = '';
			$mail->start();
			$mail->useQuotedPrintable();
			
			$mail->message = '<html><head><title>Wiki Revision</title></head><body>There is a new wiki page version ( ID ) = '.$lastID.' (From: '.$user.') <br />'
							 .$activationLink.'? Note: You need to be logged-in as admin user!<br />'
							 .'Contained text: <br /><br /><hr />'.$text.'</body></html>';
			$mail->theParts['html']['content'] = $mail->message;
			#$mail->send($mail->recipient);	
		}
	} 
}