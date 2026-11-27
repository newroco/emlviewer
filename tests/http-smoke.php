<?php

declare(strict_types=1);

// Only run against a disposable instance. The user must already exist.
require __DIR__ . '/bootstrap.php';
restore_exception_handler();

use OCP\Constants;
use OCP\Files\IRootFolder;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Lock\LockedException;
use OCP\Server;
use OCP\Share\IManager;
use OCP\Share\IShare;

$base = rtrim(getenv('NEXTCLOUD_BASE_URL') ?: 'http://127.0.0.1:8081', '/');
$username = getenv('EMLVIEWER_TEST_USER') ?: throw new RuntimeException('Set EMLVIEWER_TEST_USER');
$password = getenv('EMLVIEWER_TEST_PASSWORD') ?: throw new RuntimeException('Set EMLVIEWER_TEST_PASSWORD');
$cookie = tempnam(sys_get_temp_dir(), 'emlviewer-cookie-');
$anonymousCookie = tempnam(sys_get_temp_dir(), 'emlviewer-cookie-');
$shares = [];
$folder = null;
$manager = Server::get(IManager::class);

function request(string $url, string $cookie, ?array $form = null): array {
    $curl = curl_init($url);
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $cookie,
        CURLOPT_COOKIEFILE => $cookie, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => ['Origin: ' . preg_replace('~^(https?://[^/]+).*$~', '$1', $url)]]);
    if ($form !== null) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($form));
    }
    $body = curl_exec($curl);
    if ($body === false) {
        throw new RuntimeException(curl_error($curl));
    }
    $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $effectiveUrl = curl_getinfo($curl, CURLINFO_EFFECTIVE_URL);
    curl_close($curl);
    return [$status, $body, $effectiveUrl];
}

function check(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $message);
    }
    echo "PASS: $message\n";
}

function csrfToken(string $html): string {
    preg_match('/data-requesttoken="([^"]+)"/', $html, $match);
    return html_entity_decode($match[1] ?? throw new RuntimeException('No request token on login page'), ENT_QUOTES);
}

try {
    $user = Server::get(IUserManager::class)->get($username) ?? throw new RuntimeException('Test user not found');
    Server::get(IUserSession::class)->setUser($user);
    $folder = Server::get(IRootFolder::class)->getUserFolder($username)->newFolder('emlviewer-smoke-' . bin2hex(random_bytes(4)));
    $filename = 'Beispiel + Grüße.eml';
    $file = $folder->newFile($filename, file_get_contents(__DIR__ . '/fixtures/sample.eml'));
    $rootPath = '/' . $folder->getName() . '/' . $filename;
    foreach (['file' => $file, 'folder' => $folder, 'password' => $file] as $kind => $node) {
        $share = $manager->newShare()->setNode($node)->setShareType(IShare::TYPE_LINK)
            ->setSharedBy($username)->setPermissions(Constants::PERMISSION_READ);
        if ($kind === 'password') {
            $share->setPassword('emlviewer-smoke-password');
        }
        $shares[$kind] = $manager->createShare($share);
    }

    [, $login] = request($base . '/index.php/login', $cookie);
    [$loginStatus, , $loginUrl] = request($base . '/index.php/login', $cookie, ['user' => $username, 'password' => $password, 'requesttoken' => csrfToken($login)]);
    check($loginStatus === 200 && !str_contains($loginUrl, '/login'), 'Test user login: ' . $loginUrl);
    $route = $base . '/index.php/apps/emlviewer/';
    $own = http_build_query(['eml_file' => $rootPath]);
    [$status, $html] = request($route . 'emlparse?' . $own, $cookie);
    check($status === 200 && str_contains($html, 'EML Viewer sample email'), 'Authenticated EML preview with spaces, plus and Unicode');
    check(str_contains($html, 'Prüfempfänger Drei') && str_contains($html, 'Prüfabsender') && str_contains($html, 'Prüfkopie')
        && !str_contains($html, '=?utf-8?'), 'MIME-encoded address names are decoded in the preview');
    check(str_contains($html, 'data:image/png;base64,'), 'CID image with a different filename is embedded');
    check(str_contains($html, 'notes &amp; details.txt'), 'Attachment filenames are escaped');
    [$status, $pdf] = request($route . 'pdf?' . $own, $cookie);
    check($status === 200 && str_starts_with($pdf, '%PDF-'), 'Authenticated PDF export');
    [$status, $print] = request($route . 'emlparse?' . $own . '&print=1', $cookie);
    check($status === 200 && str_contains($print, 'emlviewer-print.js') && str_contains($print, 'printButton'), 'Print page and external print script');
    check(str_contains($print, 'emlviewer-print.css') && !str_contains($print, 'layout-base')
        && !str_contains($print, '/theming/'), 'Print page has its own stylesheet and no Nextcloud theme');
    $printText = html_entity_decode(strip_tags($print), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    check(str_contains($printText, 'Prüfempfänger Drei') && str_contains($printText, 'Prüfabsender') && str_contains($printText, 'Prüfkopie'), 'Decoded address names are retained in print/PDF header HTML');
    [$status, $attachment] = request($route . 'att?' . $own . '&att=1', $cookie);
    check($status === 200 && str_contains($attachment, 'Anhang aus dem EML Viewer Test.'), 'Attachment download');

    foreach (['file' => '', 'folder' => '/' . $filename] as $kind => $path) {
        $query = http_build_query(['share_token' => $shares[$kind]->getToken(), 'eml_file' => $path]);
        [$status, $html] = request($route . 'emlparse?' . $query, $anonymousCookie);
        check($status === 200 && str_contains($html, 'EML Viewer sample email'), 'Anonymous ' . $kind . ' share preview');
        [$status, $pdf] = request($route . 'pdf?' . $query, $anonymousCookie);
        check($status === 200 && str_starts_with($pdf, '%PDF-'), 'Anonymous ' . $kind . ' share PDF export');
    }
    $query = http_build_query(['share_token' => $shares['password']->getToken()]);
    [$status] = request($route . 'emlparse?' . $query, $anonymousCookie);
    check($status === 403, 'Password-protected share is denied before authentication');
    $shareUrl = $base . '/index.php/s/' . $shares['password']->getToken();
    [, $authPage, $authUrl] = request($shareUrl, $anonymousCookie);
    request($authUrl, $anonymousCookie, ['password' => 'emlviewer-smoke-password', 'requesttoken' => csrfToken($authPage)]);
    [$status, $pdf] = request($route . 'pdf?' . $query, $anonymousCookie);
    check($status === 200 && str_starts_with($pdf, '%PDF-'), 'Password-protected share PDF after Nextcloud authentication');
} finally {
    foreach ($shares as $share) {
        $manager->deleteShare($share);
    }
    try {
        // An HTTP response can arrive before the server releases its file locks.
        for ($attempt = 0; $folder !== null; $attempt++) {
            try {
                $folder->delete();
                break;
            } catch (LockedException $e) {
                if ($attempt >= 9) {
                    throw $e;
                }
                usleep(100000);
            }
        }
    } finally {
        unlink($cookie);
        unlink($anonymousCookie);
    }
}
