<?php

declare(strict_types=1);

namespace OCA\EmlViewer\Controller;

use Exception;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\NotFoundResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\StandaloneTemplateResponse;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\ITempManager;
use OCP\ISession;
use OCP\AppFramework\PublicShareController;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\NotPermittedException;
use OCP\Share\IShare;
use Psr\Log\LoggerInterface;
use OCP\Share\IManager;

use tidy;
use ZBateson\MailMimeParser\Message;
use ZBateson\MailMimeParser\Header\AddressHeader;
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
    private ITempManager $tempManager;
    private ISession $session;

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
					IURLGenerator $urlGenerator,
                    ITempManager $tempManager,
                    ISession $session)
    {
        parent::__construct($AppName, $request);
        $this->AppName = $AppName;
        $this->storage = $AuthorStorage;
        $this->shareManager = $shareManager;
        $this->logger = $logger;
        $this->message = null;
        $this->urlGenerator = $urlGenerator;
        $this->tempManager = $tempManager;
        $this->session = $session;
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
        $shareToken = $this->request->getParam('share_token');
        if (!empty($shareToken)) {
            $this->shareToken = $shareToken;
        }

        $emlFile = $this->request->getParam('eml_file');
        if (!empty($emlFile)) {
            $this->emlFile = $emlFile;
        } else if (!$this->shareToken) {
            throw new Exception('No eml file was sent');
        }
        if ($this->shareToken) {
            if (!$this->shareManager->shareApiEnabled() || !$this->shareManager->shareApiAllowLinks()) {
                throw new NotPermittedException('Public sharing is disabled');
            }
            $share = $this->shareManager->getShareByToken($this->shareToken);
            if (!in_array($share->getShareType(), [IShare::TYPE_LINK, IShare::TYPE_EMAIL], true)
                || $share->isExpired() || !$share->canSeeContent() || !$share->canDownload()) {
                throw new NotPermittedException('This share does not allow reading email content');
            }
            if ($share->isPasswordProtected()) {
                $tokens = json_decode($this->session->get(PublicShareController::DAV_AUTHENTICATED_FRONTEND) ?? '[]', true);
                if (!is_array($tokens) || !is_string($tokens[$this->shareToken] ?? null)
                    || !hash_equals($share->getPassword(), $tokens[$this->shareToken])) {
                    throw new NotPermittedException('Authenticate to the public share first');
                }
            }
            $node = $share->getNode();
            if ($node instanceof Folder) {
                if (empty($this->emlFile) || in_array('..', explode('/', str_replace('\\', '/', $this->emlFile)), true)) {
                    throw new NotPermittedException('Invalid path inside shared folder');
                }
                $node = $node->get($this->emlFile);
            }
            if ($node instanceof File && strtolower($node->getExtension()) === 'eml' && $node->isReadable()) {
                $contents = $node->getContent();
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
    #[PublicPage]
    #[NoCSRFRequired]
    #[NoAdminRequired]
    public function pdfPrint(): Response
    {
        try {
            $message = $this->getMessage();
            $from = $message->getHeaderValue('From');
            $to = $message->getHeaderValue('To');
            $filename = 'Message from ' . $from . ' to ' . $to . '.pdf';

            $tmpDir = $this->tempManager->getTemporaryFolder('emlviewer-pdf');
            if ($tmpDir === false) {
                throw new Exception('Could not create temporary PDF directory');
            }

            $response = $this->emlPrint(true);
            if ($response->getStatus() !== Http::STATUS_OK) {
                throw new Exception('Could not prepare email for PDF export');
            }
            $params = $response->getParams();
            $headers = new TemplateResponse($this->AppName, 'email_headers', $params, TemplateResponse::RENDER_AS_BLANK);
            $html = $this->getPrintableHtml($params['htmlContent'], $headers->render());
            $mpdf = new Mpdf([
                'tempDir' => $tmpDir,
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
            $mpdf->curlTimeout = 1000;
            $mpdf->setAutoTopMargin = 'stretch';
            $mpdf->setAutoBottomMargin = 'stretch';
            $mpdf->SetDisplayMode('fullwidth', 'single');
            $mpdf->WriteHTML($html);
            
            // Generate PDF to string instead of outputting directly
            $pdfContent = $mpdf->Output($filename, 'S'); // 'S' returns the PDF as a string
            
            // Return proper HTTP response
            $pdfResponse = new Http\DataDownloadResponse(
                $pdfContent,
                $filename,
                'application/pdf'
            );
            return $pdfResponse;
        } catch (Exception $e) {
            $this->logger->error('Error trying to render pdf: ' . $e->getMessage());
            return new DataResponse(
                array('msg' => 'Error trying to render pdf: ' . $e->getMessage()),
                $e instanceof NotPermittedException ? Http::STATUS_FORBIDDEN : Http::STATUS_INTERNAL_SERVER_ERROR
            );
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
    #[PublicPage]
    #[NoCSRFRequired]
    #[NoAdminRequired]
    public function emlPrint($print = false): TemplateResponse
    {
        if ($this->request->getParam('print') !== null) {
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
                array('eml_file' => $this->emlFile, 'share_token' => $this->shareToken, 'print' => ''));
            $params['urlPdf'] = $this->urlGenerator->linkToRoute(
                $this->AppName . '.page.pdfPrint',
                array('eml_file' => $this->emlFile, 'share_token' => $this->shareToken));
            $params['urlAttachment'] = $this->getAttachmentUrlPrefix();
            //Headers
            $params['from'] = $this->formatAddressHeader($message, 'From');
            $params['to'] = $this->formatAddressHeader($message, 'To');
            $params['cc'] = $this->formatAddressHeader($message, 'Cc');
            $params['date'] = preg_replace('/\W\w+\s*(\W*)$/', '$1', $message->getHeaderValue('Date') ?? '');
            $params['subject'] = $message->getHeaderValue('subject');
            $params['textContent'] = $message->getTextContent();
            $params['htmlContent'] = $this->getEmailHTMLContent($message);
            $params['attachments'] = Array();
            //handle attachments
            $atts = $message->getAllAttachmentParts();
            foreach ($atts as $ind => $part) {
                $params['attachments'][$ind] = self::getPartFilename($part, $ind);
            }


            if ($print) {
                $params['printScript'] = $this->urlGenerator->linkTo($this->AppName, 'js/emlviewer-print.js',
                    ['v' => hash_file('sha256', __DIR__ . '/../../js/emlviewer-print.js')]);
                $params['printStyle'] = $this->urlGenerator->linkTo($this->AppName, 'css/emlviewer-print.css',
                    ['v' => hash_file('sha256', __DIR__ . '/../../css/emlviewer-print.css')]);
                $response = new StandaloneTemplateResponse($this->AppName, 'printcontent', $params, TemplateResponse::RENDER_AS_BLANK);
            } else {
                $response = new TemplateResponse($this->AppName, 'emlcontent', $params, $renderAs = '');  // templates/emlcontent.php
            }
            $response->setContentSecurityPolicy($csp);

        } catch (Exception $e) {
            $response = new TemplateResponse($this->AppName, 'error', [
                'message' => 'Error trying to obtain eml data: ' . $e->getMessage()],
                $renderAs = '');
            $response->setStatus($e instanceof NotPermittedException ? Http::STATUS_FORBIDDEN : Http::STATUS_BAD_REQUEST);
        }
        return $response;
    }
    private function formatAddressHeader(Message $message, string $name): string
    {
        $header = $message->getHeader($name);
        if (!$header instanceof AddressHeader) {
            return '';
        }

        $addresses = [];
        foreach ($header->getAddresses() as $address) {
            $displayName = $address->getName();
            $email = $address->getEmail();
            if ($displayName === null || $displayName === '') {
                $addresses[] = $email;
                continue;
            }
            // Keep names with commas unambiguous within a recipient list.
            if (strpbrk($displayName, ',;"<>') !== false) {
                $displayName = '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $displayName) . '"';
            }
            $addresses[] = $displayName . ' <' . $email . '>';
        }
        return implode(', ', $addresses);
    }

    protected function getPrintableHtml(string $html, string $headers): string
    {
        $previous = libxml_use_internal_errors(true);
        try {
            $document = new \DOMDocument('1.0', 'UTF-8');
            $document->loadHTML('<?xml encoding="UTF-8">' . ($html ?: '<html><body></body></html>'), LIBXML_NONET);
            $headerDocument = new \DOMDocument('1.0', 'UTF-8');
            $headerDocument->loadHTML('<?xml encoding="UTF-8"><html><body>' . $headers . '</body></html>', LIBXML_NONET);
            $body = $document->getElementsByTagName('body')->item(0);
            if ($body === null) {
                $body = $document->createElement('body');
                $document->documentElement->appendChild($body);
            }
            $fragment = $document->createDocumentFragment();
            foreach ($headerDocument->getElementsByTagName('body')->item(0)->childNodes as $child) {
                $fragment->appendChild($document->importNode($child, true));
            }
            $body->insertBefore($fragment, $body->firstChild);
            foreach (iterator_to_array($document->childNodes) as $child) {
                if ($child->nodeType === XML_PI_NODE) {
                    $document->removeChild($child);
                }
            }
            return $document->saveHTML();
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
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
		$html = '';
		$htmlContent = $message->getHtmlContent();

		if (!empty($htmlContent)) {
			$html = $htmlContent;
		} else {
			$textContent = $message->getTextContent();
			if($textContent){
				$html = nl2br(htmlspecialchars($textContent, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
			}
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
            $contentId = trim($part->getHeaderValue('Content-ID') ?? '', '<>');
            if ($contentId !== '') {
                $html = str_ireplace('cid:' . $contentId, $attNewSrc, $html);
            }
            $html = str_ireplace('cid:' . $attName, $attNewSrc, $html);
        }
        // Email HTML is untrusted, especially when rendered as a full print page.
        $config = \HTMLPurifier_Config::createDefault();
        $config->set('Cache.DefinitionImpl', null);
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true, 'data' => true]);
        return (new \HTMLPurifier($config))->purify($html);
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
    #[PublicPage]
    #[NoCSRFRequired]
    #[NoAdminRequired]
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
