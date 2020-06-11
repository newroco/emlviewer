<?php

return [
    'routes' => [
	   ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
       ['name' => 'page#emlPrint', 'url' => '/emlparse', 'verb' => 'GET'],
       ['name' => 'page#pdfPrint', 'url' => '/pdf', 'verb' => 'GET']
    ]
];
