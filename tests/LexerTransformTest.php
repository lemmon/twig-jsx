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
                "{% include 'components/Alert.twig' with {'props': create_attributes({'type': 'info'})} %}",
            ],
            'dynamic variable prop' => [
                '<Alert type={userType} />',
                "{% include 'components/Alert.twig' with {'props': create_attributes({'type': userType})} %}",
            ],
            'dynamic boolean prop' => [
                '<Alert important={true} />',
                "{% include 'components/Alert.twig' with {'props': create_attributes({'important': true})} %}",
            ],
            'shorthand variable prop' => [
                '<Alert {type} />',
                "{% include 'components/Alert.twig' with {'props': create_attributes({'type': type})} %}",
            ],
            'bare boolean prop' => [
                '<Alert important />',
                "{% include 'components/Alert.twig' with {'props': create_attributes({'important': true})} %}",
            ],
            'logic / filter expression' => [
                '<Alert count={items|length} />',
                "{% include 'components/Alert.twig' with {'props': create_attributes({'count': items|length})} %}",
            ],
            'complex ternary expression' => [
                "<Alert theme={dark ? 'd' : 'l'} />",
                "{% include 'components/Alert.twig' with {'props': create_attributes({'theme': dark ? 'd' : 'l'})} %}",
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
            "{% embed 'components/Alert.twig' with {'props': create_attributes({})} %}{% block content %}hello{% endblock %}{% endembed %}",
            $this->makeLexer()->transform('<Alert>hello</Alert>'),
        );
    }

    public function testStaticAttributeGoesIntoPropsBag(): void
    {
        $this->assertSame(
            "{% include 'components/Alert.twig' with {'props': create_attributes({'class': 'shadow'})} %}",
            $this->makeLexer()->transform('<Alert class="shadow" />'),
        );
    }

    public function testBareUnknownAttributeBecomesBooleanInPropsBag(): void
    {
        $this->assertSame(
            "{% include 'components/Alert.twig' with {'props': create_attributes({'disabled': true})} %}",
            $this->makeLexer()->transform('<Alert disabled />'),
        );
    }

    public function testAllAttrsGoIntoSinglePropsBag(): void
    {
        $this->assertSame(
            "{% include 'components/Alert.twig' with {'props': create_attributes({'type': type, 'important': true, 'class': 'big'})} %}",
            $this->makeLexer()->transform('<Alert {type} important class="big" />'),
        );
    }

    public function testPropsVariableOptionRenamesBag(): void
    {
        $this->assertSame(
            "{% include 'components/Alert.twig' with {'bag': create_attributes({})} %}",
            $this->makeLexer(['props_variable' => 'bag'])->transform('<Alert />'),
        );
    }

    public function testContentBlockOptionRenamesBlock(): void
    {
        $this->assertSame(
            "{% embed 'components/Alert.twig' with {'props': create_attributes({})} %}{% block children %}hello{% endblock %}{% endembed %}",
            $this->makeLexer(['content_block' => 'children'])->transform('<Alert>hello</Alert>'),
        );
    }

    public function testDirectoryAndExtensionOptionsAreRespected(): void
    {
        $lexer = $this->makeLexer(['directory' => 'ui', 'extension' => '.html.twig']);
        $this->assertSame(
            "{% include 'ui/Alert.html.twig' with {'props': create_attributes({})} %}",
            $lexer->transform('<Alert />'),
        );
    }

    public function testPrefixOptionStripsPrefixFromResolvedName(): void
    {
        $lexer = $this->makeLexer(['prefix' => 'ui:']);
        $this->assertSame(
            "{% include 'components/Alert.twig' with {'props': create_attributes({})} %}",
            $lexer->transform('<ui:Alert />'),
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

    public function testApostropheInStaticPropValueIsEscaped(): void
    {
        $this->assertSame(
            "{% include 'components/Alert.twig' with {'props': create_attributes({'message': 'It\\'s mine'})} %}",
            $this->makeLexer()->transform('<Alert message="It\'s mine" />'),
        );
    }

    public function testBackslashInStaticPropValueIsEscaped(): void
    {
        $this->assertSame(
            "{% include 'components/Alert.twig' with {'props': create_attributes({'data-path': 'a\\\\b'})} %}",
            $this->makeLexer()->transform('<Alert data-path="a\\b" />'),
        );
    }

    public function testApostropheInUnknownStaticAttributeIsEscaped(): void
    {
        $this->assertSame(
            "{% include 'components/Alert.twig' with {'props': create_attributes({'aria-label': 'don\\'t'})} %}",
            $this->makeLexer()->transform('<Alert aria-label="don\'t" />'),
        );
    }

    // ---------------------------------------------------------------------
    // Scanner edge cases — these are the cases the old regex preprocessor
    // got wrong. Each one would have produced bogus output before #7.
    // ---------------------------------------------------------------------

    public function testTagInsideTwigOutputIsPassedThrough(): void
    {
        $input = '{{ "<Alert />" }}';
        $this->assertSame($input, $this->makeLexer()->transform($input));
    }

    public function testTagInsideTwigBlockIsPassedThrough(): void
    {
        $input = '{% set s = "<Alert />" %}';
        $this->assertSame($input, $this->makeLexer()->transform($input));
    }

    public function testTagInsideTwigCommentIsPassedThrough(): void
    {
        $input = '{# <Alert /> #}';
        $this->assertSame($input, $this->makeLexer()->transform($input));
    }

    public function testTagInsideHtmlCommentIsPassedThrough(): void
    {
        $input = '<!-- <Alert /> -->';
        $this->assertSame($input, $this->makeLexer()->transform($input));
    }

    public function testSameNameNestedTagsTrackDepthCorrectly(): void
    {
        $this->assertSame(
            "{% embed 'components/Alert.twig' with {'props': create_attributes({})} %}"
            . '{% block content %}'
            . "{% include 'components/Alert.twig' with {'props': create_attributes({})} %}"
            . '{% endblock %}{% endembed %}',
            $this->makeLexer()->transform('<Alert><Alert /></Alert>'),
        );
    }

    public function testAngleBracketInsideStringAttributeValue(): void
    {
        $this->assertSame(
            "{% include 'components/Alert.twig' with {'props': create_attributes({'data-html': 'a>b'})} %}",
            $this->makeLexer()->transform('<Alert data-html="a>b" />'),
        );
    }

    public function testAngleBracketInsideBraceExpression(): void
    {
        $this->assertSame(
            "{% include 'components/Alert.twig' with {'props': create_attributes({'cmp': a > b})} %}",
            $this->makeLexer()->transform('<Alert cmp={a > b} />'),
        );
    }

    public function testTwigOutputInsideBodyIsPassedThrough(): void
    {
        $this->assertSame(
            "{% embed 'components/Alert.twig' with {'props': create_attributes({})} %}"
            . '{% block content %}{{- title -}}{% endblock %}{% endembed %}',
            $this->makeLexer()->transform('<Alert>{{- title -}}</Alert>'),
        );
    }

    // ---------------------------------------------------------------------
    // New call-site syntax — braces for expressions, `{foo}` shorthand.
    // ---------------------------------------------------------------------

    public function testSingleQuotedStringValueIsAccepted(): void
    {
        $this->assertSame(
            "{% include 'components/Alert.twig' with {'props': create_attributes({'type': 'info'})} %}",
            $this->makeLexer()->transform("<Alert type='info' />"),
        );
    }

    public function testNestedBraceHashInsideExpression(): void
    {
        $this->assertSame(
            "{% include 'components/Alert.twig' with {'props': create_attributes({'cfg': {dark: true, fast: false}})} %}",
            $this->makeLexer()->transform('<Alert cfg={ {dark: true, fast: false} } />'),
        );
    }

    public function testClosingBraceInsideStringInsideExpression(): void
    {
        $this->assertSame(
            "{% include 'components/Alert.twig' with {'props': create_attributes({'pattern': 'a}b'})} %}",
            $this->makeLexer()->transform("<Alert pattern={'a}b'} />"),
        );
    }

    public function testNamespacedAttributesPassThrough(): void
    {
        $this->assertSame(
            "{% include 'components/Icon.twig' with {'props': create_attributes({'xlink:href': '#icon', 'wire:click': 'save'})} %}",
            $this->makeLexer()->transform('<Icon xlink:href="#icon" wire:click="save" />'),
        );
    }

    // ---------------------------------------------------------------------
    // Error paths — the scanner refuses ambiguous syntax loudly.
    // ---------------------------------------------------------------------

    public function testColonPrefixedAttributeThrowsSyntaxError(): void
    {
        $this->expectException(\Twig\Error\SyntaxError::class);
        $this->expectExceptionMessage("':foo' attribute syntax is not supported");
        $this->makeLexer()->transform('<Alert :type="x" />');
    }

    public function testUnquotedAttributeValueThrowsSyntaxError(): void
    {
        $this->expectException(\Twig\Error\SyntaxError::class);
        $this->expectExceptionMessage('Unquoted attribute values are not supported');
        $this->makeLexer()->transform('<Alert foo=bar />');
    }

    public function testUnclosedBodiedTagThrowsSyntaxError(): void
    {
        $this->expectException(\Twig\Error\SyntaxError::class);
        $this->expectExceptionMessage('Unclosed JSX tag <Alert>');
        $this->makeLexer()->transform('<Alert>oops');
    }
}
