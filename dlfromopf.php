<?php
require 'vendor/autoload.php';
ini_set("memory_limit", "512M");

use TheCodingMachine\Gotenberg\Request;
use TheCodingMachine\Gotenberg\Client;
use TheCodingMachine\Gotenberg\DocumentFactory;
use TheCodingMachine\Gotenberg\HTMLRequest;
use TheCodingMachine\Gotenberg\MergeRequest;

$client = new Client('http://localhost:3000', new \Http\Adapter\Guzzle6\Client());
define('CONTEXT', stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]));

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
    define('OPF', $params['OPF']);
    $pageName = $params['pageName'];
    $finaleFilename = $params['finaleFilename'];
} else {
    define('OPF', readline('URL de du .opf : (https://biblio.manuel-numerique.com/epubs/NATHAN/bibliomanuels/distrib_gp/2/1/10403/online/OEBPS/content.opf) ') ?: 'https://biblio.manuel-numerique.com/epubs/NATHAN/bibliomanuels/distrib_gp/2/1/10403/online/OEBPS/content.opf');
    $pageName = readline('Nom fichier page : (Page_%05d.xhtml) ') ?: 'Page_%05d.xhtml';
    $finaleFilename = readline('Nom du fichier final : (manuel.pdf) ') ?: 'manuel.pdf';
    file_put_contents($saveDir . 'params.json', json_encode(['OPF' => OPF, 'pageName' => $pageName, 'finaleFilename' => $finaleFilename]));
}

$opfFile = file_get_contents(OPF, false, CONTEXT);
$xml = new SimpleXMLElement($opfFile);

$pages = [];
foreach ($xml->manifest->item as $item) {
    $exploded = explode('/', $item['href']);
    $name = array_pop($exploded);
    if ($name === sprintf($pageName, sscanf($name, $pageName)[0])) {
        array_push($pages, strval($item['href']));
    }
}

define('BASE_URL', implode('/', array_slice(explode('/', OPF), 0, -1)) . '/');
define('ROOT_URL', implode('/', array_slice(explode('/', OPF), 0, 3)));
$html = '';
$files = [];

foreach ($pages as $pageUrl) {
    if (file_exists($saveDir . urlencode($pageUrl) . '.pdf')) {
        print("Page $pageUrl déjà téléchargée...\n");
        array_push($files, DocumentFactory::makeFromPath(urlencode($pageUrl) . '.pdf', $saveDir . urlencode($pageUrl) . '.pdf'));
        continue;
    }
    print("Téléchargement de la page $pageUrl...\n");
    $currentUrlPath = BASE_URL . implode('/', array_slice(explode('/', $pageUrl), 0, -1)) . '/';

    $html = file_get_contents(BASE_URL . $pageUrl, false, CONTEXT);
    if (!$html) {
        print("Erreur page, arrêt.\n");
        die();
    }
    
    $html = preg_replace_callback('/background-image:(.*)url\(\'(.+)\'\);/mU', function ($matches) use ($currentUrlPath) {
        $ext = pathinfo($matches[2], PATHINFO_EXTENSION);
        $prefix = $matches[2][0] === '/' ? ROOT_URL : $currentUrlPath;
        return 'background-image:' . $matches[1] . 'url(\'data:image/' . $ext . ';base64,' . base64_encode(file_get_contents($prefix . $matches[2], false, CONTEXT)) . '\');';
    }, $html);

    $html = preg_replace_callback('/<img(.+)src="(.+)"(.*)>/mU', function ($matches) use ($currentUrlPath) {
        $ext = pathinfo($matches[2], PATHINFO_EXTENSION);
        $prefix = $matches[2][0] === '/' ? ROOT_URL : $currentUrlPath;
        return '<img' . $matches[1] . 'src="data:image/' . $ext . ';base64,' . base64_encode(file_get_contents($prefix . $matches[2], false, CONTEXT)) . '"' . $matches[3] . '>';
    }, $html);
    
    preg_match_all('/href="(.+\.css)"/mU', $html, $matches);
    $assets = array_map(function ($match) use ($currentUrlPath) {
        $prefix = $match[0] === '/' ? ROOT_URL : $currentUrlPath;
        $css = file_get_contents($prefix . $match, false, CONTEXT);
        $css = preg_replace_callback('/src:.*url\(\'(.+\.ttf)\'\);/m', function ($matches) use ($currentUrlPath) {
            $prefix = $matches[1][0] === '/' ? ROOT_URL : $currentUrlPath;
            return 'url(data:font/ttf;base64,' . base64_encode(file_get_contents($prefix . $matches[1], false, CONTEXT)) . ')';
        }, $css);
        $css = preg_replace('/^.*animation.*$/m', '', $css);
        return DocumentFactory::makeFromString($match, $css);
    }, $matches[1]);

    preg_match('/<body.+style=".*width:\D*(\d+)px\D*height:\D*(\d+)\D*".*>/mU', $html, $dimensions);
    
    print("Convertion et téléchargement de la page sous `" . $saveDir . urlencode($pageUrl) . '.pdf' . "`...\n");
    $index = DocumentFactory::makeFromString('index.html', $html);
    $request = new HTMLRequest($index);
    $request->setAssets($assets);
    $request->setMargins(Request::NO_MARGINS);
    $request->setPaperWidth($dimensions[1]/96);
    $request->setPaperHeight($dimensions[2]/96);
    $request->setScale(1);
    $request->setPageRanges('1');
    $request->setWaitTimeout(30);
    $client->store($request, $saveDir . urlencode($pageUrl) . '.pdf');
    array_push($files, DocumentFactory::makeFromPath(urlencode($pageUrl) . '.pdf', $saveDir . urlencode($pageUrl) . '.pdf'));
}

print("Fin du manuel, fusion des pages...\n");
$request = new MergeRequest($files);
$request->setWaitTimeout(120);
$client->store($request, $saveDir . $finaleFilename);
print("Fin.");
