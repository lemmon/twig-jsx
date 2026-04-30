<?php

declare(strict_types=1);

namespace Lemmon\TwigJsx\Tests;

use Lemmon\TwigJsx\JSXPreLexer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * Golden-string tests for {@see JSXPreLexer::transform()}.
 *
 * These pin the rewritten Twig source for each prop form documented in the
 * README, so any change to the lexer's output is an explicit decision rather
 * than an accidental regression.
 */
final class LexerTransformTest extends TestCase
{
    private function makeLexer(array $options = []): JSXPreLexer
    {
        return new JSXPreLexer(new Environment(new ArrayLoader([])), $options);
    }

    public static function readmePropFormsProvider(): array
    {
        return [
            'static prop' => [
                '<Alert type="info" />',
                "{% include 'components/Alert.twig' with {'type': 'info', 'attributes': create_attributes({})} %}",
            ],
            'dynamic variable prop' => [
                '<Alert :type="userType" />',
                "{% include 'components/Alert.twig' with {'type': userType, 'attributes': create_attributes({})} %}",
            ],
            'dynamic boolean prop' => [
                '<Alert :important="true" />',
                "{% include 'components/Alert.twig' with {'important': true, 'attributes': create_attributes({})} %}",
            ],
            'shorthand variable prop' => [
                '<Alert :type />',
                "{% include 'components/Alert.twig' with {'type': type, 'attributes': create_attributes({})} %}",
            ],
            'shorthand boolean (known prop)' => [
                '<Alert important />',
                "{% include 'components/Alert.twig' with {'important': true, 'attributes': create_attributes({})} %}",
            ],
            'logic / filter expression' => [
                '<Alert :count="items|length" />',
                "{% include 'components/Alert.twig' with {'count': items|length, 'attributes': create_attributes({})} %}",
            ],
            'complex ternary expression' => [
                '<Alert :theme="dark ? \'d\' : \'l\'" />',
                "{% include 'components/Alert.twig' with {'theme': dark ? 'd' : 'l', 'attributes': create_attributes({})} %}",
            ],
        ];
    }

    #[DataProvider('readmePropFormsProvider')]
    public function testReadmePropForms(string $input, string $expected): void
    {
        $this->assertSame($expected, $this->makeLexer()->transform($input));
    }

    public function testBodiedTagBecomesEmbed(): void
    {
        $this->assertSame(
            "{% embed 'components/Alert.twig' with {'attributes': create_attributes({})} %}{% block content %}hello{% endblock %}{% endembed %}",
            $this->makeLexer()->transform('<Alert>hello</Alert>')
        );
    }

    public function testUnknownStaticAttributeGoesToAttributesBucket(): void
    {
        $this->assertSame(
            "{% include 'components/Alert.twig' with {'attributes': create_attributes({'class': 'shadow'})} %}",
            $this->makeLexer()->transform('<Alert class="shadow" />')
        );
    }

    public function testValuelessUnknownAttributeBecomesBooleanInBucket(): void
    {
        $this->assertSame(
            "{% include 'components/Alert.twig' with {'attributes': create_attributes({'disabled': true})} %}",
            $this->makeLexer()->transform('<Alert disabled />')
        );
    }

    public function testKnownAndUnknownPropsAreSeparated(): void
    {
        $this->assertSame(
            "{% include 'components/Alert.twig' with {'type': type, 'important': true, 'attributes': create_attributes({'class': 'big'})} %}",
            $this->makeLexer()->transform('<Alert :type important class="big" />')
        );
    }

    public function testAttrNameOptionRenamesAttributesBucket(): void
    {
        $this->assertSame(
            "{% include 'components/Alert.twig' with {'props': create_attributes({})} %}",
            $this->makeLexer(['attr_name' => 'props'])->transform('<Alert />')
        );
    }

    public function testDirectoryAndExtensionOptionsAreRespected(): void
    {
        $lexer = $this->makeLexer(['directory' => 'ui', 'extension' => '.html.twig']);
        $this->assertSame(
            "{% include 'ui/Alert.html.twig' with {'attributes': create_attributes({})} %}",
            $lexer->transform('<Alert />')
        );
    }

    public function testPrefixOptionStripsPrefixFromResolvedName(): void
    {
        $lexer = $this->makeLexer(['prefix' => 'ui:']);
        $this->assertSame(
            "{% include 'components/Alert.twig' with {'attributes': create_attributes({})} %}",
            $lexer->transform('<ui:Alert />')
        );
    }

    public function testLowercaseHtmlTagsArePassedThrough(): void
    {
        $input = '<div class="x"><p>hi</p></div>';
        $this->assertSame($input, $this->makeLexer()->transform($input));
    }

    public function testPlainTwigIsPassedThrough(): void
    {
        $input = '{{ greeting }} <p>{% if user %}{{ user.name }}{% endif %}</p>';
        $this->assertSame($input, $this->makeLexer()->transform($input));
    }
}
