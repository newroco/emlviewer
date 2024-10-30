<?php

declare(strict_types=1);

namespace OCA\EmlViewer\Controller;

use Exception;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IURLGenerator;
use Psr\Log\LoggerInterface;
use OCP\Share\IManager;

use tidy;
use ZBateson\MailMimeParser\Message;
use Mpdf\Mpdf;

use OCA\EmlViewer\Storage\AuthorStorage;

/**
 * @psalm-suppress UnusedClass
 */
class PageController extends Controller {
	public const FIXED_EML_SIZE_CONFIG_KEY = 'fixed_eml_size';

	public const CONFIG_KEYS = [
		self::FIXED_EML_SIZE_CONFIG_KEY,
	];

	protected $AppName;
    private $logger;
    private $storage;
    private $shareManager;
    private $message;
    private $urlGenerator;
    private $emlFile;
    private $shareToken;

	    /**
     * PageController constructor.
     * @param $AppName
     * @param IRequest $request
     * @param AuthorStorage $AuthorStorage
     * @param IManager $shareManager
     */
    public function __construct($AppName,
					IRequest $request,
					AuthorStorage $AuthorStorage,
					IManager $shareManager,
					LoggerInterface $logger,
					IURLGenerator $urlGenerator)
    {
        parent::__construct($AppName, $request);
        $this->AppName = $AppName;
        $this->storage = $AuthorStorage;
        $this->shareManager = $shareManager;
        $this->logger = $logger;
        $this->message = null;
        $this->urlGenerator = $urlGenerator;
    }

    /**
     * @return mixed
     */
    public function getMessage()
    {
        if ($this->message === null) {
            $this->parseEml();
        }
        return $this->message;
    }

    /**
     * @return Message
     * @throws Exception
     */
    protected function parseEml()
    {
        $this->shareToken = null;
        $contents = '';
        if (isset($_GET['share_token']) && !empty($_GET['share_token'])) {
            $this->shareToken = $_GET['share_token'];
        }

        if (isset($_GET['eml_file']) && !empty($_GET['eml_file'])) {
            $this->emlFile = $_GET['eml_file'];
        } else if (!$this->shareToken) {
            throw new Exception('No eml file was sent');
        }
        if ($this->shareToken) {
            /* shared file or directory */
            $share = $this->shareManager->getShareByToken($this->shareToken);
            $node = $share->getNode();
            $type = $node->getType();

            /* shared directory, need file path to continue, */
            if ($type !== \OCP\Files\FileInfo::TYPE_FOLDER) {
                $extension = strtolower($node->getExtension());
                if ($extension == 'eml') {
                    $contents = $node->getContent();
                }
            } else {//this is a directory
                $fileNode = $node->get($this->emlFile);
                $extension = strtolower($fileNode->getExtension());
                if ($extension == 'eml') {
                    $contents = $fileNode->getContent();
                }
            }
        } else {
            $contents = $this->storage->emlFileContent($this->emlFile);
        }
        if (!$contents) {
            throw new Exception('Could not load contents of file' . $this->emlFile);
        }

        $this->message = Message::from($contents, true);
        return $this->message;
    }

	 /**
     * @return Response
     * @PublicPage
     * @NoCSRFRequired
     * @NoAdminRequired
     */
    public function pdfPrint(): Response
    {
        try {
            $message = $this->getMessage();
            $from = $message->getHeaderValue('From');
            $to = $message->getHeaderValue('To');
            $filename = 'Message from ' . $from . ' to ' . $to . '.pdf';

            $response = $this->emlPrint(true);
            $html = $response->render();
            $formerErrorReporting = error_reporting(0);
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
                'author' => 'nextcloud ' . $this->AppName
            ]);
            //$mpdf->showImageErrors = true;
            $mpdf->curlAllowUnsafeSslRequests = true;
            $mpdf->curlTimeout = 1000;
            $mpdf->setAutoTopMargin = 'stretch';
            $mpdf->setAutoBottomMargin = 'stretch';
            $mpdf->SetDisplayMode('fullwidth', 'single');
            $mpdf->WriteHTML($html);
            $mpdf->Output($filename, 'I');
            error_reporting($formerErrorReporting);
            exit;
        } catch (Exception $e) {
            return new DataResponse(array('msg' => 'Error trying to render pdf: ' . $e->getMessage()), Http::STATUS_OK);
        }
    }
 /**
     * @PublicPage
     * @NoAdminRequired
     * @NoCSRFRequired
     * @param bool $print
     *
     * @return TemplateResponse
     */
    public function emlPrint($print = false): TemplateResponse
    {
        if (isset($_GET['print'])) {
            $print = true;
        }
        try {
            $message = $this->getMessage();
            $params = Array();
            $csp = new ContentSecurityPolicy();
            $csp->addAllowedImageDomain('*');
            $csp->addAllowedMediaDomain('*');

            //URLs
            $params['urlPrinter'] = $this->urlGenerator->linkToRoute(
                $this->AppName . '.page.emlPrint',
                array('eml_file' => $this->emlFile, 'print' => ''));
            $params['urlPdf'] = $this->urlGenerator->linkToRoute(
                $this->AppName . '.page.pdfPrint',
                array('eml_file' => $this->emlFile));
            $params['urlAttachment'] = $this->getAttachmentUrlPrefix();
            //Headers
            $params['from'] = $message->getHeaderValue('From');
            $params['to'] = $message->getHeaderValue('To');
            $params['date'] = preg_replace('/\W\w+\s*(\W*)$/', '$1', $message->getHeaderValue('Date'));
            $params['subject'] = $message->getHeaderValue('subject');
            $params['textContent'] = $message->getTextContent();
            $params['nonce'] = \OC::$server->getContentSecurityPolicyNonceManager()->getNonce();
            $params['htmlContent'] = $this->getEmailHTMLContent($message);
            $params['attachments'] = Array();
            //handle attachments
            $atts = $message->getAllAttachmentParts();
            foreach ($atts as $ind => $part) {
                $params['attachments'][$ind] = self::getPartFilename($part);
            }


            if ($print) {
                $headers = new TemplateResponse($this->AppName, 'email_headers', $params, $renderAs = '');
                //$csp->addAllowedScriptDomain('\'unsafe-inline\'');
                $headers->setContentSecurityPolicy($csp);
                $headersHtml = $headers->render();

                $whatToInsertBefore = null;

                if ($params['htmlContent']) {
                    $doc = new \DOMDocument();
                    // modify state
                    $libxml_previous_state = libxml_use_internal_errors(true);
                    $doc->loadHTML($params['htmlContent']);
                    //ignore HTML errors
                    libxml_clear_errors();
                    // restore state
                    libxml_use_internal_errors($libxml_previous_state);

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
                    if (!$whatToInsertBefore) {
                        $body = $doc->getElementsByTagName('body')->item(0);
                        $whatToInsertBefore = $body->firstChild;
                    }
                    $whatToInsertBefore->parentNode->insertBefore($fragment, $whatToInsertBefore);

                    $params['htmlContent'] = $doc->saveHTML();
                } else {
                    $params['htmlContent'] = 'no content in e-mail body';
                }

                $response = new TemplateResponse($this->AppName, 'printcontent', $params, $renderAs = 'blank');  // templates/printcontent.php
            } else {
                $response = new TemplateResponse($this->AppName, 'emlcontent', $params, $renderAs = '');  // templates/emlcontent.php
            }
            $response->setContentSecurityPolicy($csp);

        } catch (Exception $e) {
            $response = new TemplateResponse($this->AppName, 'error', [
                'message' => 'Error trying to obtain eml data: ' . $e->getMessage()],
                $renderAs = '');
        }
        return $response;
    }
	private function getAttachmentUrlPrefix()
    {
        $urlAttachment = $this->urlGenerator->linkToRoute(
                $this->AppName . '.page.attachment',
                array(
                    'eml_file' => $this->emlFile,
                    'share_token' => $this->shareToken
                )) . '&att=';
        return $urlAttachment;
    }

	protected function getEmailHTMLContent(Message $message)
    {
		$htmlContent = $message->getHtmlContent();

		if (!empty($htmlContent)) {
			$html = str_replace('"', '\'', $htmlContent);
		} else {
			$html = nl2br($message->getTextContent());
		}

        if (class_exists('tidy')) {
            $tidy = new tidy();
            //Specify configuration
            $config = array(
                'indent' => true,
                'output-xhtml' => true,
                'wrap' => 200);
            $html = $tidy->repairString($html, $config);
        } else {
            $this->logger->warning('php-tidy was not found on this server. Please install so ' . $this->AppName . ' can produce better PDFs.');
        }
        //handle attachment CID urls
        $atts = $message->getAllAttachmentParts();
        $urlAttachment = $this->getAttachmentUrlPrefix();
        foreach ($atts as $index => $part) {
            $attName = self::getPartFilename($part, $index);
            $attNewSrc = $urlAttachment . $index;
            $contentType = $part->getHeaderValue('Content-Type');
            if (stripos($contentType, 'image') !== FALSE) {
                //base64 encode images, for better PDF export support and display performance
                $content = $part->getContent();
                $attNewSrc = 'data:' . $contentType . ';base64,' . base64_encode($content);
            }
            $html = preg_replace('/' . preg_quote('cid:' . $attName) . '/ixm', $attNewSrc, $html);
        }
        if(function_exists('mb_convert_encoding')){
            return mb_convert_encoding($html, 'html-entities', 'UTF-8');
        } else {
            return $html;
        }
    }

    /**
     * @param MessagePart $part
     * @param int $index
     * @return mixed
     */
    protected static function getPartFilename($part, $index = 0)
    {
        $contentID = $part->getHeaderValue('Content-ID');
        if (!$contentID) {
            $contentID = '__unknown_filename' . $index;
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

    /**
     * @param int $att
     * @return mixed
     * @PublicPage
     * @NoCSRFRequired
     * @NoAdminRequired
     */
    public function attachment(int $att = 0): Response
    {
        $att = intval($att);
        $message = $this->getMessage();
        $part = $message->getAttachmentPart($att);
        if ($part) {
            $content = $part->getContent();
            return new DataDownloadResponse($content, self::getPartFilename($part), $part->getHeaderValue('Content-Type'));
        }
        return new NotFoundResponse();
    }

    protected function extractTableInTable($element)
    {
        //$doc = $element->ownerDocument;
        $arrTrNodes = Array();
        $arrTdNodes = Array();
        $table = $element->getElementsByTagName('table');
        if ($table->length > 0) {
            $table = $table->item(0);
            $tbody = $table->getElementsByTagName('tbody');
            if ($tbody->length > 0) {
                foreach ($tbody->childNodes as $child) {
                    if ($child->nodeName === 'tr') {
                        $arrTrNodes[] = $child;
                    }
                }
                if (count($arrTrNodes) === 1) {
                    $tr = $arrTrNodes[0];
                    foreach ($tr->childNodes as $child) {
                        if ($child->nodeName === 'td') {
                            $arrTdNodes[] = $child;
                        }
                    }
                    if (count($arrTdNodes) === 1) {
                        $td = $arrTdNodes[0];
                        return $this->extractTableInTable($td);
                    }
                }
            }
        } else {
            $table = null;
        }
        return $table;
    }
}
