# 0001 — No string interpolation in quoted prop values

- **Status:** Accepted
- **Date:** 2026-05-30
- **Affects:** `JsxSourceTransformer::parseAttributeValue()`

## TL;DR

A quoted prop value (`title="…"`) is a **static string**. We do **not**
interpolate `{{ … }}` inside it. Instead, a quoted value that contains a
`{{ … }}` output tag is rejected at transform time with a `SyntaxError` that
points the author at the expression form. Dynamic values use `{ … }`:

```twig
<Alert class={'alert-' ~ type} />     {# do this #}
<Alert class="alert-{{ type }}" />    {# SyntaxError, with a pointer to the line above #}
```

This reverses the older silent behaviour, where `class="alert-{{ type }}"`
passed the **literal** string `alert-{{ type }}` into the props bag — a quiet
footgun for anyone with Vue/Angular/Twig-attribute muscle memory.

## Context

Twig interpolates `{{ … }}` inside attribute values on plain HTML elements
(`<div title="Hello {{ name }}">`). It is natural to expect the same on a
component tag (`<Alert title="Hello {{ name }}" />`). Before this decision the
scanner treated the whole quoted value as opaque text, so the `{{ name }}` was
passed through verbatim — the component received the literal characters
`Hello {{ name }}`, never the value of `name`. No error, no warning.

We ran an experiment to add real interpolation and evaluated three
implementations before concluding that the feature itself was not worth
shipping. This document records the options, the questions each one raised, and
why we landed on a rejection instead.

## Options considered

### 1. Hand-rolled mini-lexer → `~` concatenation

Scan the value, split it on `{{ … }}`, and emit a Twig concatenation:

```
class="alert-{{ type }}"   →   'class': 'alert-' ~ (type)
title="{{ name }}"         →   'title': '' ~ (name)        (always a string)
```

- **Pros:** self-contained expression (composes anywhere — embeds, loops,
  nested tags); transparent escaping (raw string, component escapes on render);
  compile-time errors in the author's coordinates.
- **Cons:** ~140 lines re-implementing a slice of Twig's tokenizer — bracket
  depth, string-literal skipping, whitespace-control (`{{- -}}`/`{{~ ~}}`)
  handling. Every Twig nuance is a place to diverge.

### 2. Translate `{{ x }}` → Twig native interpolation `"#{x}"`

Rewrite to a double-quoted Twig string and let Twig lex the interior.

- **Verdict: dominated.** To rewrite `{{ expr }}` → `#{expr}` you must still
  find the matching `}}`, so you keep option 1's entire scanner *and* add
  re-escaping of `"`, `\`, and `#{` in the static segments — plus the footgun
  that a literal `#{` in static text becomes interpolation. More complexity than
  option 1 for no benefit; both hand the expression interior to Twig anyway.

### 3. `{% set %}` capture + `autoescape false` + `jsx_plain()`

Inject the raw value verbatim into a captured block and let Twig render it:

```twig
{% set __jsx_attr_1 %}{% autoescape false %}Hello {{ name }}{% endautoescape %}{% endset %}
{% include 'components/Alert.twig' with {
  'props': create_attributes({'title': jsx_plain(__jsx_attr_1)})
} %}
```

- **Pros:** deletes the scanner entirely — Twig does all lexing, so there is
  zero fidelity risk; trim markers and filters and everything else "just work".
- **Cons:**
  - **Escaping is a silent-XSS hazard.** A captured block is a `Markup` object,
    which Twig will not re-escape. `autoescape false` (don't escape during
    capture) **plus** `jsx_plain()` (strip the `Markup` so the component escapes
    once, in its own context) is the only correct combination. Drop either and
    you get double-escaping or unescaped output. The failure mode is a security
    bug, not a visible error.
  - Output shape changes from a single expression to a statement sequence,
    needing a collision-safe counter and a custom `jsx_plain` runtime function.
  - Errors move to render time, pointing into generated source.
  - Per-render cost (output buffering + `Markup` allocation + a function call).

## The cross-cutting questions

Whichever option we picked, the same questions kept surfacing — and that was the
real signal:

- **Escaping model.** Who escapes, and in which context? (Option 3 makes the
  answer a load-bearing, easy-to-break invariant.)
- **Trim markers.** `title="Hello {{- name -}} !"` — does it render `Hello Eugen !`
  (option 1, marker stripped from the expression but literal whitespace kept) or
  `HelloEugen!` (real Twig)? Option 1's middle state honours neither cleanly;
  honouring it means replicating Twig's whitespace semantics.
- **`{% … %}` statements.** On a real HTML element, Twig executes them. Do we?
  Option 1 leaves them literal; the README's "just like a plain HTML attribute"
  framing implies they should run. This is the next domino after trim markers.
- **The parity promise.** If we claim "a prop value behaves like a Twig HTML
  attribute", then trim markers, statements, and every other lexer nuance must
  match — a standing obligation to *be* Twig. Only option 3 can keep that
  promise, and only by accepting all of its costs.

## Decision

**Do not interpolate quoted prop values. Reject `{{ … }}` inside them with a
redirect error.**

The deciding observation: interpolation adds **no capability** — the expression
form already covers every case, usually more cleanly.

```twig
<Alert class={'alert-' ~ type} />
<Alert title={"Hello #{name}"} />
<Alert title={'Hello ' ~ name ~ ', ' ~ count ~ ' messages'} />
```

It is also **not the JSX idiom** (React uses `className={...}`, never
`class="x-{val}"`), so for a library called *twig-jsx* the brace form is the
on-brand answer. Adding a second, subtly different attribute syntax — one that
is always a string and escapes via `~`, versus one that passes a raw typed
value — means two overlapping forms with different rules, which the docs would
have to spend a paragraph disambiguating. That paragraph is the smell.

So instead of building (and forever maintaining) interpolation, we fix the one
real problem — the silent literal passthrough — with a five-line guard that
turns the footgun into a teaching error.

### What this looks like

- `parseAttributeValue()` throws a `SyntaxError` when a quoted value contains
  `{{`, naming the attribute and pointing at the expression form.
- The rejection is **scoped to `{{` output tags only.** `{% … %}` statements and
  bare single braces carry no interpolation and stay literal — a quoted value is
  a static string. (Pinned by `testStatementTagInQuotedValueStaysLiteral` and
  `testSingleBracesInQuotedValueStayLiteral`.)
- Escape hatch for a genuine literal `{{ … }}`: wrap it in a quoted expression,
  `tpl={'{{ name }}'}`.

## Consequences

- **Breaking** relative to the old silent passthrough: a template that relied on
  `prop="{{ x }}"` reaching the component as literal text now errors. That
  reliance was almost certainly a latent bug; the error names the fix.
- The scanner stays small and the question surface collapses: no escaping model
  to defend, no trim-marker semantics, no statement handling, no parity promise.
- Users compose dynamic values with the single expressive form (`{ … }`) that
  already handles strings, numbers, arrays, filters, and ternaries.

## When to revisit

Reconsider only if real usage shows the **multi-hole static-text** case
(`title="Hello {{ name }}, you have {{ count }} messages"`) is common enough in
*props* that the expression form's quote-and-concat noise is a genuine pain —
and even then, prefer a deliberately minimal interpolation (only `{{ }}`, trim
markers and `{% %}` rejected, no "HTML-attribute parity" claim) over the full
capture-based approach. Adopt option 3 only if you decide to promise full Twig
template-text semantics inside prop values — a commitment to mirror Twig's lexer
indefinitely, with the escaping invariant treated as security-critical and
tested adversarially.
