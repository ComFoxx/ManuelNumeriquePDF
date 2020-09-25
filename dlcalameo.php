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
    $book = $params['BOOK'];
    $auth = $params['auth'];
    $pageName = $params['pageName'];
    $filename = $params['filename'];
    $finaleFilename = $params['finaleFilename'];
} else {
    $book = readline('Identifiant du livre : (0005967291057b38a5183) ') ?: '0005967291057b38a5183';
    $auth = readline('ID authentification : (PWSuccrUBE1Z) ') ?: 'PWSuccrUBE1Z';
    $pageName = readline('Nom fichier page : (p%d.svgz) ') ?: 'p%d.svgz';
    $filename = readline('Nom des fichiers des pages : (page_%03d.pdf) ') ?: 'page_%03d.pdf';
    $finaleFilename = readline('Nom du fichier final : (manuel.pdf) ') ?: 'manuel.pdf';
    file_put_contents($saveDir . 'params.json', json_encode(['BOOK' => $book, 'auth' => $auth, 'pageName' => $pageName, 'filename' => $filename, 'finaleFilename' => $finaleFilename]));
}

$jsonBook = json_decode(substr(file_get_contents('https://d.calameo.com/3.0.0/book.php?callback=_jsonBook&bkcode=' . $book . '&authid=' . $auth), 10, -1), true);

define('BASE_URL', $jsonBook['content']['domains']['svg'] . $jsonBook['content']['key'] . '/');
$files = [];

for ($page = 1; $page <= $jsonBook['content']['document']['pages']; $page++) {
    if (file_exists($saveDir . sprintf($filename, $page))) {
        print("Page $page déjà téléchargée...\n");
        array_push($files, DocumentFactory::makeFromPath(sprintf($filename, $page), $saveDir . sprintf($filename, $page)));
        continue;
    }
    print("Téléchargement de la page $page...\n");
    $html = file_get_contents(BASE_URL . sprintf($pageName, $page));
    if (!$html) {
        print("Erreur page, arrêt.\n");
        die();
    }

    preg_match('/viewBox="\s*\d+\s+\d+\s+(\d+)\s+(\d+)\s*"/', $html, $dimensions);
    
    print("Convertion et téléchargement de la page sous `" . $saveDir . sprintf($filename, $page) . "`...\n");
    $index = DocumentFactory::makeFromString('index.html', '<html><head><meta charset="utf-8"></head><body style="margin: 0;">' . $html . '</body></html>');
    $request = new HTMLRequest($index);
    $request->setMargins(Request::NO_MARGINS);
    $request->setPaperWidth($dimensions[1]/96);
    $request->setPaperHeight($dimensions[2]/96);
    $request->setScale(1);
    $request->setPageRanges('1');
    $request->setWaitTimeout(30);
    $client->store($request, $saveDir . sprintf($filename, $page));
    array_push($files, DocumentFactory::makeFromPath(sprintf($filename, $page), $saveDir . sprintf($filename, $page)));
}

print("Fin du manuel, fusion des pages...\n");
$request = new MergeRequest($files);
$request->setWaitTimeout(120);
$client->store($request, $saveDir . $finaleFilename);
print("Fin.");
