<?php

return [
    'routes' => [
	   ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
       ['name' => 'page#parseEml', 'url' => '/emlparse', 'verb' => 'POST'],
       ['name' => 'page#pdfPrint', 'url' => '/pdf', 'verb' => 'GET']
    ]
];
