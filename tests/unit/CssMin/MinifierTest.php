<?php

use DemocracyPoll\Libs\CssMin\Minifier;
use PHPUnit\Framework\TestCase;

class MinifierTest extends TestCase {

	/**
	 * @covers \DemocracyPoll\Libs\CssMin\Minifier::run()
	 * @dataProvider run_minifies_css__data
	 */
	public function test__run_minifies_css( string $css, string $expected ): void {
		$minifier = new Minifier( false );

		$this->assertSame( $expected, $minifier->run( $css ) );
	}

	public function run_minifies_css__data(): array {
		return [
			'whitespace and punctuation' => [
				"\n.foo, .bar { color: red; padding: 1px 2px; }\n",
				'.foo,.bar{color:red;padding:1px 2px}',
			],
			'comments and empty rules' => [
				'.unused { } /* ordinary */ .used { color: red; }',
				'.used{color:red}',
			],
			'colors, zero units, and repeated shorthand values' => [
				'.box { color: rgb(255, 255, 255); margin: 0px 0px 0px 0px; font-weight: bold; }',
				'.box{color:#fff;margin:0;font-weight:700}',
			],
			'quoted content remains intact' => [
				'.icon { content: "a  b; /* text */"; color: white; }',
				'.icon{content:"a  b; /* text */";color:white}',
			],
			'critical at-rules are ordered first' => [
				'.x { color: red; } @namespace svg url(http://example.com); @import url(theme.css); @charset "UTF-8";',
				'@charset "UTF-8";@import url(theme.css);@namespace svg url(http://example.com);.x{color:red}',
			],
			'nested selectors' => [
				'.card { color: white; > .title { font-weight: bold; } .meta { color: gray; } &:hover { color: rgb(255, 0, 0); } }',
				'.card{color:white;>.title{font-weight:700}.meta{color:gray}&:hover{color:#f00}}',
			],
			'nested conditional rule' => [
				'.card { display: block; @media (width >= 40rem) { display: grid; gap: 0px; } }',
				'.card{display:block;@media (width>=40rem){display:grid;gap:0px}}',
			],
			'custom properties and variable fallbacks' => [
				':root { --brand-color: #ff0000; --space: 1rem; } .button { color: var(--brand-color, currentColor); padding-inline: var(--space); }',
				':root{--brand-color:#f00;--space:1rem}.button{color:var(--brand-color,currentColor);padding-inline:var(--space)}',
			],
			'custom property names remain case-sensitive' => [
				':root { --BrandColor: red; } .button { color: var(--BrandColor); }',
				':root{--BrandColor:red}.button{color:var(--BrandColor)}',
			],
			'modern math functions preserve required operator whitespace' => [
				'.layout { width: min(100% - 2rem, 80ch); font-size: clamp(1rem, 2vw + 0.5rem, 2rem); margin-inline: max(1rem, calc((100vw - 80rem) / 2)); }',
				'.layout{width:min(100% - 2rem,80ch);font-size:clamp(1rem,2vw + .5rem,2rem);margin-inline:max(1rem,calc((100vw - 80rem)/2))}',
			],
			'modern color functions' => [
				'.colors { color: rgb(255 0 0 / 50%); border-color: oklch(62% 0.2 250); background: color(display-p3 1 0 0 / 0.8); }',
				'.colors{color:rgb(255 0 0/50%);border-color:oklch(62% .2 250);background:color(display-p3 1 0 0/0.8)}',
			],
			'cascade layers and container queries' => [
				'@layer reset, components; @layer components { .card { container-type: inline-size; } } @container (width > 30rem) { .card { display: grid; } }',
				'@layer reset,components;@layer components{.card{container-type:inline-size}}@container (width>30rem){.card{display:grid}}',
			],
			'modern length units' => [
				'.units { width: 0cqw; height: 0dvh; top: 0svmin; left: 0lvmax; margin: 0rex; padding: 0rcap; right: 0ric; bottom: 0rlh; }',
				'.units{width:0;height:0;top:0;left:0;margin:0;padding:0;right:0;bottom:0}',
			],
			'property registration and feature query' => [
				'@property --angle { syntax: "<angle>"; inherits: false; initial-value: 0deg; } @supports (selector(:has(*))) { .card:has(img) { display: grid; } }',
				'@property --angle{syntax:"<angle>";inherits:false;initial-value:0deg}@supports (selector(:has(*))){.card:has(img){display:grid}}',
			],
		];
	}

	/**
	 * @covers \DemocracyPoll\Libs\CssMin\Minifier::run()
	 */
	public function test__run_returns_empty_string_for_unsupported_input(): void {
		$minifier = new Minifier( false );

		$this->assertSame( '', $minifier->run() );
		$this->assertSame( '', $minifier->run( 123 ) );
	}

	/**
	 * @covers \DemocracyPoll\Libs\CssMin\Minifier::run()
	 * @covers \DemocracyPoll\Libs\CssMin\Minifier::removeImportantComments()
	 */
	public function test__important_comments_can_be_kept_or_removed(): void {
		$minifier = new Minifier( false );
		$css = '/*! license */ .x { color: red; }';

		$this->assertSame( "/*! license */\n.x{color:red}", $minifier->run( $css ) );

		$minifier->removeImportantComments();

		$this->assertSame( '.x{color:red}', $minifier->run( $css ) );
	}

	/**
	 * @covers \DemocracyPoll\Libs\CssMin\Minifier::keepSourceMapComment()
	 * @covers \DemocracyPoll\Libs\CssMin\Minifier::run()
	 */
	public function test__source_map_comment_can_be_preserved(): void {
		$minifier = new Minifier( false );
		$minifier->keepSourceMapComment();

		$this->assertSame(
			".x{color:red}\n/*# sourceMappingURL=app.css.map */",
			$minifier->run( '.x { color: red; } /*# sourceMappingURL=app.css.map */' )
		);
	}

	/**
	 * @covers \DemocracyPoll\Libs\CssMin\Minifier::run()
	 * @covers \DemocracyPoll\Libs\CssMin\Minifier::setLineBreakPosition()
	 */
	public function test__long_output_can_be_split_between_rules(): void {
		$minifier = new Minifier( false );
		$minifier->setLineBreakPosition( 10 );

		$this->assertSame(
			".a{color:red}\n.b{color:blue}\n.c{color:green}",
			$minifier->run( '.a { color: red; } .b { color: blue; } .c { color: green; }' )
		);
	}

}
