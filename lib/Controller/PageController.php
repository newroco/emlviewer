<?php

namespace OCA\EmlViewer\Controller;

use DOMDocument;
use Exception;

if ((@include_once __DIR__ . '/../../vendor/autoload.php')===false) {
    throw new Exception('Cannot include autoload. Did you run install dependencies using composer?');
}

use OCP\IRequest;
use \OCP\IURLGenerator;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Controller;
use OCA\EmlViewer\Storage\AuthorStorage;
use \OCP\Files\NotFoundException;
use \OCP\ILogger;

use tidy;
use ZBateson\MailMimeParser\Message;
use \Mpdf\Mpdf;


class PageController extends Controller {
    private $logger;
	private $userId;
    private $storage;
    private $message;
    private $urlGenerator;
    private $emlFile;
    protected $AppName;

    /**
     * @return mixed
     */
    public function getMessage()
    {
        if($this->message === null){
            $this->parseEml();
        }
        return $this->message;
    }

    /**
     * PageController constructor.
     * @param $AppName
     * @param IRequest $request
     * @param $UserId
     * @param AuthorStorage $AuthorStorage
     */
	public function __construct($AppName, IRequest $request, $UserId,
                                AuthorStorage $AuthorStorage,
                                ILogger $logger,
                                IURLGenerator $urlGenerator){
		parent::__construct($AppName, $request);
		$this->AppName = $AppName;
        $this->storage = $AuthorStorage;
		$this->userId = $UserId;
		$this->logger = $logger;
		$this->message = null;
        $this->urlGenerator = $urlGenerator;
	}


	/**
     * @PublicPage
	 * @NoAdminRequired
     * @NoCSRFRequired
     * @param bool $print
     *
     * @return TemplateResponse
	 */
	public function emlPrint($print = false){
        if(isset($_GET['print'])){
            $print = true;
        }
        try{
            $message = $this->getMessage();
            $params = Array();
            $csp = new ContentSecurityPolicy();
            $csp->addAllowedImageDomain('*');
            $csp->addAllowedMediaDomain('*');

            //URLs
            $params['urlPrinter'] = $this->urlGenerator->linkToRoute(
                $this->AppName.'.page.emlPrint',
                array('eml_file' => $this->emlFile,'print'=>''));
            $params['urlPdf'] = $this->urlGenerator->linkToRoute(
                $this->AppName.'.page.pdfPrint',
                array('eml_file' => $this->emlFile));
            $params['urlAttachment'] = $this->urlGenerator->linkToRoute(
                $this->AppName.'.page.attachment',
                array('eml_file' => $this->emlFile)).'&att=';
            //Headers
            $params['from'] = $message->getHeaderValue('From');
            $params['to'] = $message->getHeaderValue('To');
            $params['date'] = preg_replace('/\W\w+\s*(\W*)$/', '$1', $message->getHeaderValue('Date'));
            $params['subject']  = $message->getHeaderValue('subject');
            $params['textContent'] = $message->getTextContent();
            $params['nonce'] = \OC::$server->getContentSecurityPolicyNonceManager()->getNonce();
            $params['htmlContent'] = $this->getEmailHTMLContent($message);
            $params['attachments'] = Array();
            //handle attachments
            $atts = $message->getAllAttachmentParts();
            foreach ($atts as $ind => $part) {
                $params['attachments'][$ind] = self::getPartFilename($part);
            }


            if($print){
                $headers = new TemplateResponse($this->AppName, 'email_headers', $params, $renderAs = '');
                //$csp->addAllowedScriptDomain('\'unsafe-inline\'');
                $headers->setContentSecurityPolicy($csp);
                $headersHtml = $headers->render();

                $whatToInsertBefore = null;

                $doc = new DOMDocument();
                $doc->loadHTML($params['htmlContent']);

                //fix usual e-mail table in table pattern
               /* $table = $doc->getElementsByTagName('table');
                if($table->length > 0) {
                    $table = $table->item(0);
                    $innerTable = $this->extractTableInTable($table);
                    if ($table !== $innerTable) {
                        //$innerTable2 = $innerTable->cloneNode(true);
                        //$table->parentNode->appendChild($innerTable2);
                        //$table->parentNode->removeChild($table);
                        //$whatToInsertBefore = $innerTable2;
                        $whatToInsertBefore = $innerTable;
                    }
                }*/

                $fragment = $doc->createDocumentFragment();
                $fragment->appendXML($headersHtml);
                if(!$whatToInsertBefore){
                    $body = $doc->getElementsByTagName('body')->item(0);
                    $whatToInsertBefore = $body->firstChild;
                }
                $whatToInsertBefore->parentNode->insertBefore($fragment, $whatToInsertBefore);

                 $params['htmlContent'] = $doc->saveHTML();

                $response = new TemplateResponse($this->AppName, 'printcontent', $params, $renderAs = 'blank');  // templates/printcontent.php
            }else {
                $response = new TemplateResponse($this->AppName, 'emlcontent', $params, $renderAs = '');  // templates/emlcontent.php
            }
            $response->setContentSecurityPolicy($csp);

        }catch(Exception $e){
            $response = new TemplateResponse($this->AppName, 'error', [
                'message' => 'Error trying to obtain eml data: '. $e->getMessage()],
                $renderAs = '');
        }
        return $response;
    }


	/**
     * @NoCSRFRequired
	 * @NoAdminRequired
	 */
	public function pdfPrint() {
        try{
            $message = $this->getMessage();
            $from = $message->getHeaderValue('From');
            $to = $message->getHeaderValue('To');
            $filename = 'Message from ' . $from . ' to ' . $to . '.pdf';

            $response = $this->emlPrint(true);
            $html = $response->render();
            $mpdf = new Mpdf([
                'tempDir' => __DIR__ . '/../../tmp',
                'mode' => 'UTF-8',
                'format' => 'A4-P',
                'default_font' => 'arial',
                'margin_left' => 5,
                'margin_right' => 5,
                //'debug' => true,
                'allow_output_buffering' => true,
                'simpleTable' => false,
                'use_kwt' => true,
                'ignore_table_widths' => true,
                'shrink_tables_to_fit' => false,
                //'table_error_report' =>true,
                'allow_charset_conversion' => true,
                //'CSSselectMedia' => 'screen',
                'author' => 'nextcloud '.$this->AppName
            ]);
            //$mpdf->showImageErrors = true;
            $mpdf->curlAllowUnsafeSslRequests = true;
            $mpdf->curlTimeout = 1000;
            $mpdf->setAutoTopMargin = 'stretch';
            $mpdf->setAutoBottomMargin = 'stretch';
            $mpdf->SetDisplayMode('fullwidth', 'single');
            $mpdf->WriteHTML($html);
            $mpdf->Output($filename, 'I');
        } catch (Exception $e) {
            echo 'Error trying to render pdf: ' . $e->getMessage();
        }
	}

    /**
     * @NoCSRFRequired
     * @NoAdminRequired
     * @return mixed
     * @throws Exception
     */
	public function attachment(){
        if(isset($_GET['att']) && $_GET['att'] !==''){
            $att = intval($_GET['att']);
        }else{
            throw new Exception('No attachment id was sent');
        }

        $message = $this->getMessage();
        $part = $message->getAttachmentPart($att);
        if($part){
            $content = $part->getContent();
            header("Content-type: ".$part->getHeaderValue('Content-Type'));
            header("Cache-Control: no-store, no-cache");
            header('Content-Disposition: attachment; filename="'.self::getPartFilename($part).'"');
            header('Content-Length: '.mb_strlen($content, '8bit'));
            echo $content;
        }
        return null;
    }

    /**
     * @return Message
     * @throws Exception
     */
    protected function parseEml(){
        if(isset($_GET['eml_file']) && !empty($_GET['eml_file'])){
            $this->emlFile = urldecode($_GET['eml_file']);
        }else{
            throw new Exception('No eml file was sent');
        }

        $contents = $this->storage->emlFileContent($this->emlFile);
        if(!$contents){
            throw new Exception('Could not load contents of file'.$this->emlFile);
        }

        $this->message = Message::from($contents);
        return $this->message;
    }

	protected function getEmailHTMLContent(Message $message){
        $html = str_replace('"', '\'', $message->getHtmlContent());
        if(class_exists('tidy')){
            $tidy = new tidy();
            //Specify configuration
            $config = array(
                'indent' => true,
                'output-xhtml' => true,
                'wrap' => 200);
            $html = $tidy->repairString($html, $config);
        }else{
            $this->logger->warning('php-tidy was not found on this server. Please install so '.$this->AppName.' can produce better PDFs.');
        }
        //handle attachment CID urls
        $atts = $message->getAllAttachmentParts();
        $urlAttachment = $this->urlGenerator->linkToRoute(
            $this->AppName.'.page.attachment',
            array('eml_file' => $this->emlFile,'att'=>''));
        foreach ($atts as $index => $part) {
            $attName = self::getPartFilename($part,$index);
            $attNewSrc = $urlAttachment.$index;
            $contentType = $part->getHeaderValue('Content-Type');
            if(stripos($contentType,'image') !==  FALSE){
                //base64 encode images, for better PDF export support and display performance
                $content = $part->getContent();
                $attNewSrc = 'data:'.$contentType.';base64,'.base64_encode($content);
            }
            $html = preg_replace('/'.preg_quote('cid:'.$attName).'/ixm',$attNewSrc, $html);
        }
        if(empty($html)){
            $html = nl2br($message->getTextContent());
        }
	    return $html;
    }

    /**
     * @param MessagePart $part
     * @param int $index
     * @return mixed
     */
    protected static function getPartFilename($part,$index = 0)
    {
        $contentID = $part->getHeaderValue('Content-ID');
        if(!$contentID){
            $contentID = '__unknown_filename'.$index;
        }
        $filename = $part->getHeaderParameter(
            'Content-Type',
            'name',
            $part->getHeaderParameter(
                'Content-Disposition',
                'filename',
                $contentID
            )
        );
        return $filename;
    }

    protected function extractTableInTable($element){
        //$doc = $element->ownerDocument;
        $arrTrNodes = Array();
        $arrTdNodes = Array();
        $table = $element->getElementsByTagName('table');
        if($table->length > 0) {
            $table = $table->item(0);
            $tbody = $table->getElementsByTagName('tbody');
            if ($tbody->length > 0) {
                foreach ($tbody->childNodes as $child ) {
                    if ( $child->nodeName === 'tr' ) {
                        $arrTrNodes[] = $child;
                    }
                }
                if (count($arrTrNodes) === 1) {
                    $tr = $arrTrNodes[0];
                    foreach ($tr->childNodes as $child ) {
                        if ( $child->nodeName === 'td' ) {
                            $arrTdNodes[] = $child;
                        }
                    }
                    if (count($arrTdNodes) === 1) {
                        $td = $arrTdNodes[0];
                        return $this->extractTableInTable($td);
                    }
                }
            }
        }else{
            $table = null;
        }
        return $table;
    }

	public function index() {
		return new TemplateResponse($this->AppName, 'index');  // templates/index.php
	}
}