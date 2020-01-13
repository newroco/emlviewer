<?php

require __DIR__ . '/vendor/autoload.php';

use ZBateson\MailMimeParser\MailMimeParser;
use ZBateson\MailMimeParser\Message;

$eml_file = $_POST['eml_file'];

if(isset($_POST['eml_file']) && !empty($_POST['eml_file'])){
	$message = Message::from(urldecode($eml_file));
	$email = '';

	$email .= '<p style="margin-top: 30px;">From: <strong>'.$message->getHeaderValue('From').'</strong></p>';
	$email .= '<p>To: <strong>'.$message->getHeaderValue('To').'</strong></p>';
	$email .= '<p>Date received: <strong>'.preg_replace('/\W\w+\s*(\W*)$/', '$1', $message->getHeaderValue('Date')).'</strong></p>';
	if(!empty($message->getTextContent()))
		$email .= '<button type="button" style="width: 150px;" id="toggle-text-content">Show content</button><div id="email-text-content" class="fade-out">Message:<br> '.$message->getTextContent().'</div>';
	if(!empty($message->getHtmlContent()))
		$email .= '<div style="flex: 1;">Content:<br> <iframe id="email-html-content" srcdoc="'.str_replace('"', '\'', $message->getHtmlContent()).'" style="width: 100%;min-height: 200px;height: 100%;"></iframe></div>';

	echo $email;
} else {
	echo 'Could not generate email preview';
}
