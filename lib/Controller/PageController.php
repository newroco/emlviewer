<?php
namespace OCA\EmlViewer\Controller;
require $_SERVER['DOCUMENT_ROOT'] . "/apps/emlviewer/ajax/vendor/autoload.php";

use OCP\IRequest;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;

use Dompdf\Dompdf;
use ZBateson\MailMimeParser\MailMimeParser;
use ZBateson\MailMimeParser\Message;


class PageController extends Controller {
	private $userId;

	public function __construct($AppName, IRequest $request, $UserId){
		parent::__construct($AppName, $request);
		$this->userId = $UserId;
	}

	/**
	 * CAUTION: the @Stuff turns off security checks; for this page no admin is
	 *          required and no CSRF check. If you don't know what CSRF is, read
	 *          it up in the docs or you might create a security hole. This is
	 *          basically the only required method to add this exemption, don't
	 *          add it to any other method if you don't exactly know what it does
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	
	public function parseEml($eml_file) {
		$eml_file = $_POST['eml_file'];
		if(isset($_POST['eml_file']) && !empty($_POST['eml_file'])){
		$message = Message::from(urldecode($eml_file));
		$email = '';

		$email .= '<p style="margin-top: 30px;">From: <strong>'.$message->getHeaderValue('From').'</strong></p>';
		$email .= '<p>To: <strong>'.$message->getHeaderValue('To').'</strong></p>';
		$email .= '<p>Date received: <strong>'.preg_replace('/\W\w+\s*(\W*)$/', '$1', $message->getHeaderValue('Date')).'</strong></p>';
		if(!empty($message->getTextContent()))
			$email .= '<button type="button" style="width: 150px;" id="toggle-text-content">Show content</button><button type="button" style="width: 150px;" id="make-pdf">Download as PDF</button><div id="email-text-content" class="fade-out">Message:<br> '.$message->getTextContent().'</div>';
		if(!empty($message->getHtmlContent()))
			$email .= '<div style="flex: 1;">Content:<br> <iframe id="email-html-content" srcdoc="'.str_replace('"', '\'', $message->getHtmlContent()).'" style="width: 100%;min-height: 200px;height: 100%;"></iframe></div>';
			return $email;
		} else {
			echo 'Could not generate email preview';
		}
	}

	/**
	 * CAUTION: the @Stuff turns off security checks; for this page no admin is
	 *          required and no CSRF check. If you don't know what CSRF is, read
	 *          it up in the docs or you might create a security hole. This is
	 *          basically the only required method to add this exemption, don't
	 *          add it to any other method if you don't exactly know what it does
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */

	public function pdfPrint($eml_file) {
		$eml_file = $_POST['eml_file'];
		$message = Message::from(urldecode($eml_file));
		$filename = "Message from " .$message->getHeaderValue('From');

		$email = str_replace('"', '\'', $message->getHtmlContent());

		$document = new Dompdf();
		$document->loadHtml($email);
		$document->setPaper('A4','portrait');
		$document->render();
		$document->stream($filename,array("Attachment"=>1));
	}

	public function index() {
		return new TemplateResponse('emlviewer', 'index');  // templates/index.php
	}
}