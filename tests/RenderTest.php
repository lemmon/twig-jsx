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
            'components/Alert.twig' => '<div class="alert alert-{{ type|default("info") }}">'
                . '{% if title is defined %}<strong>{{ title }}</strong>{% endif %}'
                . '{% block content %}{{ message|default("") }}{% endblock %}'
                . '</div>',
            'page' => '<Alert title="Hello" message="World" />',
        ]);

        $this->assertSame(
            '<div class="alert alert-info"><strong>Hello</strong>World</div>',
            $twig->render('page')
        );
    }

    public function testDynamicPropResolvesFromContext(): void
    {
        $twig = $this->makeTwig([
            'components/Alert.twig' => '<div class="alert-{{ type }}">{% block content %}{{ message }}{% endblock %}</div>',
            'page' => '<Alert :type message="hi" />',
        ]);

        $this->assertSame(
            '<div class="alert-success">hi</div>',
            $twig->render('page', ['type' => 'success'])
        );
    }

    public function testShorthandBooleanForKnownProp(): void
    {
        $twig = $this->makeTwig([
            'components/Alert.twig' => '<div>{{ important ? "yes" : "no" }}</div>',
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

    public function testHtmlAttributesArePassedThroughViaAttributesBucket(): void
    {
        $twig = $this->makeTwig([
            'components/Alert.twig' => '<div {{ attributes|render }}>x</div>',
            'page' => '<Alert class="shadow" data-id="42" />',
        ]);

        $this->assertSame(
            '<div class="shadow" data-id="42">x</div>',
            $twig->render('page')
        );
    }

    public function testValuelessUnknownAttributeRendersAsHtmlBoolean(): void
    {
        $twig = $this->makeTwig([
            'components/Alert.twig' => '<button {{ attributes|render }}>x</button>',
            'page' => '<Alert disabled />',
        ]);

        $this->assertSame('<button disabled>x</button>', $twig->render('page'));
    }

    public function testExceptRemovesAttributeFromBucket(): void
    {
        $twig = $this->makeTwig([
            'components/Alert.twig' => '<div class="own {{ attributes.class }}" {{ attributes.except("class")|render }}>x</div>',
            'page' => '<Alert class="extra" data-id="42" />',
        ]);

        $this->assertSame(
            '<div class="own extra" data-id="42">x</div>',
            $twig->render('page')
        );
    }
}
