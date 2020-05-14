<?php

namespace OCA\EmlViewer\Controller;

use Exception;

if ((@include_once __DIR__ . '/../../vendor/autoload.php')===false) {
    throw new Exception('Cannot include autoload. Did you run install dependencies using composer?');
}

use OCP\IRequest;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Controller;
use OCA\EmlViewer\Storage\AuthorStorage;
use \OCP\Files\NotFoundException;

use tidy;
use ZBateson\MailMimeParser\Message;
use \Mpdf\Mpdf;


class PageController extends Controller {
	private $userId;
    private $storage;

    /**
     * PageController constructor.
     * @param $AppName
     * @param IRequest $request
     * @param $UserId
     * @param AuthorStorage $AuthorStorage
     */
	public function __construct($AppName, IRequest $request, $UserId, AuthorStorage $AuthorStorage){
		parent::__construct($AppName, $request);
        $this->storage = $AuthorStorage;
		$this->userId = $UserId;
	}

	/**
     * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
     *
     * @return TemplateResponse
	 */
	public function parseEml(){
        $eml_file = '';
        $err = null;
        if(isset($_POST['eml_file'])){
            $eml_file = urldecode($_POST['eml_file']);
        };
        try {
            //$contents = file_get_contents($eml_file);
            $contents = $this->storage->emlFileContent($eml_file);
            if(!$contents){
                return 'Could not load contents of file'.$eml_file;
            }
        } catch (Exception $e) {
            return 'Error trying to obtain eml data: '. $e->getMessage();
        }
        if($contents){
            try {
                $message = Message::from($contents);
                $params = Array();
                $params['from'] = $message->getHeaderValue('From');
                $params['to'] = $message->getHeaderValue('To');
                $params['date'] = preg_replace('/\W\w+\s*(\W*)$/', '$1', $message->getHeaderValue('Date'));
                $params['textContent'] = $message->getTextContent();
                $params['htmlContent'] = str_replace('"', '\'', $message->getHtmlContent());
                $response = new TemplateResponse('emlviewer', 'emlcontent', $params, $renderAs = '');  // templates/emlcontent.php

                $policy = new ContentSecurityPolicy();
                //$policy->addAllowedChildSrcDomain('\'self\'');
                //allow loading external images
                $policy->addAllowedChildSrcDomain('*');
                $policy->addAllowedFontDomain('*');
                $policy->addAllowedFontDomain('blob:');
                $policy->addAllowedFontDomain('data:');
                $policy->addAllowedImageDomain('*');
                $policy->allowEvalScript(false);
                $response->setContentSecurityPolicy($policy);

            }catch(Exception $e){
                $err = 'Error trying to obtain eml data: '.$e->getMessage();
            }
        }else{
            $err = 'No eml file sent';
        }
        if($err){
            return $err;
        }

        return $response;
    }
    /*{

	}*/

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function pdfPrint($eml_file) {
        $err = null;
        if(isset($_GET['eml_file']) && !empty($_GET['eml_file'])) {
            try {
                $eml_file = urldecode($_GET['eml_file']);
                $contents = $this->storage->emlFileContent($eml_file);
                if (!$contents) {
                    return 'Could not load contents of file' . $eml_file;
                }
            } catch (Exception $e) {
                return 'Error trying to obtain eml data: ' . get_class($e) . ' ' . $e->getMessage();
            }
            if ($contents) {
                try {
                    $message = Message::from($contents);
                    $from = $message->getHeaderValue('From');
                    $to = $message->getHeaderValue('To');
                    $filename = 'Message from ' . $from . ' to ' . $to . '.pdf';
                    $email = str_replace('"', '\'', $message->getHtmlContent());
                    $tidy = new tidy();
                    //Specify configuration
                    $config = array(
                        'indent' => true,
                        'output-xhtml' => true,
                        'wrap' => 200);
                    $email = $tidy->repairString($email, $config);

                    $mpdf = new Mpdf([
                        'tempDir' => __DIR__ . '/../../tmp',
                        'mode' => 'UTF-8',
                        'format' => 'A4-P',
                        'margin_left' => 5,
                        'margin_right' => 5,
                        //'debug' => true,
                        'allow_output_buffering' => true,
                        //'simpleTable' => true,
                        'author' => 'Eml Viewer'
                    ]);
                    $mpdf->curlAllowUnsafeSslRequests = true;
                    $mpdf->curlTimeout = 1;
                    $mpdf->setAutoTopMargin = 'stretch';
                    $mpdf->setAutoBottomMargin = 'stretch';
                    $mpdf->SetDisplayMode('fullwidth', 'single');
                    $mpdf->WriteHTML($email);
                    $mpdf->Output($filename, 'I');
                } catch (Exception $e) {
                    $err = 'Error trying to render pdf: ' . $e->getMessage();
                }
            } else {
                $err = 'No eml file sent';
            }
        }
        if($err){
            return $err;
        }
	}

	public function index() {
		return new TemplateResponse('emlviewer', 'index');  // templates/index.php
	}
}