<?php
namespace OCA\EmlViewer\Controller;

use Exception;

if ((@include_once __DIR__ . '/../../vendor/autoload.php')===false) {
    throw new Exception('Cannot include autoload. Did you run install dependencies using composer?');
}

use OCP\IRequest;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;

use Dompdf\Dompdf;
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
		$err = null;
		if(isset($_POST['eml_file']) && !empty($_POST['eml_file'])){
            try {
                $message = Message::from(urldecode($eml_file));
                $params = Array();
                $params['from'] = $message->getHeaderValue('From');
                $params['to'] = $message->getHeaderValue('To');
                $params['date'] = preg_replace('/\W\w+\s*(\W*)$/', '$1', $message->getHeaderValue('Date'));
                $params['textContent'] = $message->getTextContent();
                $params['htmlContent'] = str_replace('"', '\'', $message->getHtmlContent());
                return new TemplateResponse('emlviewer', 'emlcontent', $params, $renderAs = '');  // templates/emlcontent.php
            }catch(Exception $e){
                $err = 'Error trying to obtain eml data: '.$e->getMessage();
            }
		}else{
            $err = 'No eml file sent';
        }
		if($err){
		    return $err;
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
        $err = null;
        if(isset($_POST['eml_file']) && !empty($_POST['eml_file'])) {
            try {
                $message = Message::from(urldecode($eml_file));
                $filename = "Message from " . $message->getHeaderValue('From');
                $email = str_replace('"', '\'', $message->getHtmlContent());

                $document = new Dompdf();
                $document->loadHtml($email);
                $document->setPaper('A4', 'portrait');
                $document->render();
                $document->stream($filename, array("Attachment" => 1));
            }catch(Exception $e){
                $err = 'Error trying to obtain eml data: '.$e->getMessage();
            }
        }else{
            $err = 'No eml file sent';
        }
        if($err){
            return $err;
        }
	}

	public function index() {
		return new TemplateResponse('emlviewer', 'index');  // templates/index.php
	}
}