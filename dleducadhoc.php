<?php
require 'vendor/autoload.php';
ini_set("memory_limit", "512M");

use TheCodingMachine\Gotenberg\Request;
use TheCodingMachine\Gotenberg\Client;
use TheCodingMachine\Gotenberg\DocumentFactory;
use TheCodingMachine\Gotenberg\HTMLRequest;
use TheCodingMachine\Gotenberg\MergeRequest;

$client = new Client('http://localhost:3000', new \Http\Adapter\Guzzle6\Client());

$saveDir = readline('Dossier de sauvegarde : (./manuel/) ') ?: './manuel/';
if ($saveDir[strlen($saveDir)-1] !== '/') $saveDir .= '/';

$useJson = false;
if (!is_dir($saveDir)) {
    mkdir($saveDir, 077, true);
} elseif (file_exists($saveDir . 'params.json')) {
    $params = json_decode(file_get_contents($saveDir . 'params.json'), true);
    $useJson = in_array(readline("Fichier de paramètres trouvé, l'utiliser ? (O)"), ['', 'O', 'o']);
}

if ($useJson && isset($params)) {
    define('BOOK', $params['BOOK']);
    $pageName = $params['pageName'];
    $filename = $params['filename'];
    $finaleFilename = $params['finaleFilename'];
} else {
    define('BOOK', readline('Identifiant du livre : (9782401073463) ') ?: '9782401073463');
    $pageName = readline('Nom fichier page : (page%d.xhtml) ') ?: 'page%d.xhtml';
    $filename = readline('Nom des fichiers des pages : (page_%03d.pdf) ') ?: 'page_%03d.pdf';
    $finaleFilename = readline('Nom du fichier final : (manuel.pdf) ') ?: 'manuel.pdf';
    file_put_contents($saveDir . 'params.json', json_encode(['BOOK' => BOOK, 'pageName' => $pageName, 'filename' => $filename, 'finaleFilename' => $finaleFilename]));
}

define('ROOT_URL', 'https://educadhoc.prod.hachette-livre.fr');
define('BASE_URL', ROOT_URL . '/product/' . BOOK . '/show-page/'); // extract/complet ?
$page = 1;
$html = '';
$files = [];

while (true) {
    if (file_exists($saveDir . sprintf($filename, $page))) {
        print("Page $page déjà téléchargée...\n");
        array_push($files, DocumentFactory::makeFromPath(sprintf($filename, $page), $saveDir . sprintf($filename, $page)));
        $page++;
        continue;
    }
    print("Téléchargement de la page $page...\n");
    $html = file_get_contents(BASE_URL . sprintf($pageName, $page));
    if (strlen($html) < 330) {
        print("Page inexistante\n");
        break;
    }

    $html = preg_replace_callback('/url\((fonts\/font-.+\.otf)\)/m', function ($matches) {
        return 'url(data:font/otf;base64,' . base64_encode(file_get_contents(BASE_URL . $matches[1])) . ')';
    }, $html);
    
    $html = preg_replace_callback('/<img(.+)src="(.+)"(.*)>/mU', function ($matches) {
        $ext = pathinfo($matches[2], PATHINFO_EXTENSION);
        $prefix = $matches[2][0] === '/' ? ROOT_URL : BASE_URL;
        return '<img' . $matches[1] . 'src="data:image/' . $ext . ';base64,' . base64_encode(file_get_contents($prefix . $matches[2])) . '"' . $matches[3] . '>';
    }, $html);
    
    preg_match_all('/href="(.+\.css)"/mU', $html, $matches);
    $assets = array_map(function ($match) {
        $prefix = $match[0] === '/' ? ROOT_URL : BASE_URL;
        return DocumentFactory::makeFromString($match, file_get_contents($prefix . $match));
    }, $matches[1]);

    preg_match('/<body.+style=".*width:(\d+)px;height:(\d+)px;.*".*>/mU', $html, $dimensions);
    
    print("Convertion et téléchargement de la page sous `" . $saveDir . sprintf($filename, $page) . "`...\n");
    $index = DocumentFactory::makeFromString('index.html', $html);
    $request = new HTMLRequest($index);
    $request->setAssets($assets);
    $request->setMargins(Request::NO_MARGINS);
    $request->setPaperWidth($dimensions[1]/96);
    $request->setPaperHeight($dimensions[2]/96);
    $request->setScale(1);
    $request->setPageRanges('1');
    $request->setWaitTimeout(30);
    $client->store($request, $saveDir . sprintf($filename, $page));
    array_push($files, DocumentFactory::makeFromPath(sprintf($filename, $page), $saveDir . sprintf($filename, $page)));
    $page++;
}

print("Fin du manuel, fusion des pages...\n");
$request = new MergeRequest($files);
$request->setWaitTimeout(120);
$client->store($request, $saveDir . $finaleFilename);
print("Fin.");
