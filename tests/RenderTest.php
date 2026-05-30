<?php

declare(strict_types=1);

namespace Lemmon\TwigJsx\Tests;

use Lemmon\TwigJsx\AttributeExtension;
use Lemmon\TwigJsx\JSXPreLexer;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * End-to-end render tests: drive a full Twig environment with the JSX lexer
 * and assert on the final HTML.
 *
 * Templates are inlined via ArrayLoader so these tests don't depend on
 * fixture files that other commits may need to edit.
 */
final class RenderTest extends TestCase
{
    private function makeTwig(array $templates, array $options = []): Environment
    {
        $twig = new Environment(new ArrayLoader($templates), ['cache' => false]);
        $twig->addExtension(new AttributeExtension());
        $twig->setLexer(new JSXPreLexer($twig, $options));

        return $twig;
    }

    public function testStaticPropsRender(): void
    {
        $twig = $this->makeTwig([
            'components/Alert.twig' =>
                '<div class="alert alert-{{ props.type|default("info") }}">'
                    . '{% if props.title is defined %}<strong>{{ props.title }}</strong>{% endif %}'
                    . '{% block content %}{{ props.message|default("") }}{% endblock %}'
                    . '</div>',
            'page' => '<Alert title="Hello" message="World" />',
        ]);

        $this->assertSame(
            '<div class="alert alert-info"><strong>Hello</strong>World</div>',
            $twig->render('page'),
        );
    }

    public function testDynamicPropResolvesFromContext(): void
    {
        $twig = $this->makeTwig([
            'components/Alert.twig' => '<div class="alert-{{ props.type }}">{% block content %}{{ props.message }}{% endblock %}</div>',
            'page' => '<Alert {type} message="hi" />',
        ]);

        $this->assertSame(
            '<div class="alert-success">hi</div>',
            $twig->render('page', ['type' => 'success']),
        );
    }

    public function testShorthandBooleanPropRenders(): void
    {
        $twig = $this->makeTwig([
            'components/Alert.twig' => '<div>{{ props.important ? "yes" : "no" }}</div>',
            'page' => '<Alert important />',
        ]);

        $this->assertSame('<div>yes</div>', $twig->render('page'));
    }

    public function testBodiedTagRendersChildrenIntoContentBlock(): void
    {
        $twig = $this->makeTwig([
            'components/Alert.twig' => '<div>[{% block content %}{% endblock %}]</div>',
            'page' => '<Alert><p>child</p></Alert>',
        ]);

        $this->assertSame('<div>[<p>child</p>]</div>', $twig->render('page'));
    }

    public function testContentBlockOptionRendersIntoCustomBlock(): void
    {
        $twig = $this->makeTwig([
            'components/Box.twig' => '<div>[{% block children %}{% endblock %}]</div>',
            'page' => '<Box><p>child</p></Box>',
        ], ['content_block' => 'children']);

        $this->assertSame('<div>[<p>child</p>]</div>', $twig->render('page'));
    }

    public function testHtmlAttributesArePassedThroughViaPropsBag(): void
    {
        $twig = $this->makeTwig([
            'components/Alert.twig' => '<div {{ props|spread }}>x</div>',
            'page' => '<Alert class="shadow" data-id="42" />',
        ]);

        $this->assertSame(
            '<div class="shadow" data-id="42">x</div>',
            $twig->render('page'),
        );
    }

    public function testValuelessUnknownAttributeRendersAsHtmlBoolean(): void
    {
        $twig = $this->makeTwig([
            'components/Alert.twig' => '<button {{ props|spread }}>x</button>',
            'page' => '<Alert disabled />',
        ]);

        $this->assertSame('<button disabled>x</button>', $twig->render('page'));
    }

    public function testExceptRemovesKeyFromPropsBag(): void
    {
        $twig = $this->makeTwig([
            'components/Alert.twig' => '<div class="own {{ props.class }}" {{ props.except("class")|spread }}>x</div>',
            'page' => '<Alert class="extra" data-id="42" />',
        ]);

        $this->assertSame(
            '<div class="own extra" data-id="42">x</div>',
            $twig->render('page'),
        );
    }

    public function testApostropheInStaticPropValueRendersCorrectly(): void
    {
        $twig = $this->makeTwig([
            'components/Alert.twig' => '<p>{{ props.message }}</p>',
            'page' => '<Alert message="It\'s mine" />',
        ]);

        $this->assertSame('<p>It&#039;s mine</p>', $twig->render('page'));
    }

    public function testBackslashInStaticAttributeRendersCorrectly(): void
    {
        $twig = $this->makeTwig([
            'components/Alert.twig' => '<a {{ props|spread }}>x</a>',
            'page' => '<Alert data-path="a\\b" />',
        ]);

        $this->assertSame('<a data-path="a\\b">x</a>', $twig->render('page'));
    }

    public function testBraceExpressionPropRenders(): void
    {
        $twig = $this->makeTwig([
            'components/Alert.twig' => '<div>{{ props.message }}</div>',
            'page' => '<Alert message={items|length} />',
        ]);

        $this->assertSame(
            '<div>3</div>',
            $twig->render('page', ['items' => [1, 2, 3]]),
        );
    }

    public function testSameNameNestedTagsRender(): void
    {
        $twig = $this->makeTwig([
            'components/Alert.twig' => '[{% block content %}{% endblock %}]',
            'page' => '<Alert><Alert /></Alert>',
        ]);

        $this->assertSame('[[]]', $twig->render('page'));
    }

    public function testTagInsideTwigStringIsNotTransformed(): void
    {
        $twig = $this->makeTwig([
            'page' => '{{ "<Alert />" }}',
        ]);

        $this->assertSame('<Alert />', $twig->render('page'));
    }
}
