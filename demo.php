<?php

require __DIR__ . '/vendor/autoload.php';

use Lemmon\TwigJsx\JSXPreLexer;
use Lemmon\TwigJsx\AttributeExtension;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

// 1. Setup Twig
$loader = new FilesystemLoader(__DIR__ . '/templates');
$twig = new Environment($loader, [
    'debug' => true,
    'cache' => false,
]);

$twig->addExtension(new AttributeExtension());

$twig->setLexer(new JSXPreLexer($twig, [
    'attr_name' => 'props',
]));

// 2. Define some test data
$data = [
    'user' => [
        'name' => 'John Doe',
    ],
    'type' => 'success',
];

// 3. Template string
$templateString = '
<h1>Welcome, {{ user.name }}</h1>

<!-- Using SHORTHANDS -->
<Alert title="Shorthand Demo" :type important message="This used :type and important shorthands!" />

<hr>

<Alert type="warning" class="mt-4 shadow">
    <p>Standard syntax still works fine.</p>
</Alert>
';

// 4. Render
echo "--- RENDERED OUTPUT ---\n";
echo $twig->createTemplate($templateString)->render($data);
echo "\n-----------------------\n";
