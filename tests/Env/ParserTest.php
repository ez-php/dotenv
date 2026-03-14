<?php

declare(strict_types=1);

namespace Tests\Env;

use EzPhp\Env\Parser;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

/**
 * Class ParserTest
 *
 * @package Tests\Env
 */
#[CoversClass(Parser::class)]
final class ParserTest extends TestCase
{
    private Parser $parser;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->parser = new Parser();
    }

    // ─── Basic parsing ────────────────────────────────────────────────────────

    /**
     * @return void
     */
    public function test_parses_simple_key_value(): void
    {
        $result = $this->parser->parse('KEY=value');

        $this->assertSame(['KEY' => 'value'], $result);
    }

    /**
     * @return void
     */
    public function test_parses_multiple_lines(): void
    {
        $result = $this->parser->parse("FOO=bar\nBAZ=qux");

        $this->assertSame(['FOO' => 'bar', 'BAZ' => 'qux'], $result);
    }

    /**
     * @return void
     */
    public function test_skips_blank_lines(): void
    {
        $result = $this->parser->parse("\n\nKEY=value\n\n");

        $this->assertSame(['KEY' => 'value'], $result);
    }

    /**
     * @return void
     */
    public function test_skips_comment_lines(): void
    {
        $result = $this->parser->parse("# this is a comment\nKEY=value");

        $this->assertSame(['KEY' => 'value'], $result);
    }

    /**
     * @return void
     */
    public function test_skips_lines_without_equals(): void
    {
        $result = $this->parser->parse("NOEQUALS\nKEY=value");

        $this->assertSame(['KEY' => 'value'], $result);
    }

    /**
     * @return void
     */
    public function test_skips_invalid_key_names(): void
    {
        $result = $this->parser->parse("123KEY=value\nVALID=ok");

        $this->assertSame(['VALID' => 'ok'], $result);
    }

    /**
     * @return void
     */
    public function test_empty_value(): void
    {
        $result = $this->parser->parse('KEY=');

        $this->assertSame(['KEY' => ''], $result);
    }

    // ─── export prefix ────────────────────────────────────────────────────────

    /**
     * @return void
     */
    public function test_strips_export_prefix(): void
    {
        $result = $this->parser->parse('export KEY=value');

        $this->assertSame(['KEY' => 'value'], $result);
    }

    // ─── Unquoted values ─────────────────────────────────────────────────────

    /**
     * @return void
     */
    public function test_trims_unquoted_value(): void
    {
        $result = $this->parser->parse('KEY=  value  ');

        $this->assertSame(['KEY' => 'value'], $result);
    }

    /**
     * @return void
     */
    public function test_strips_inline_comment_from_unquoted_value(): void
    {
        $result = $this->parser->parse('KEY=value # inline comment');

        $this->assertSame(['KEY' => 'value'], $result);
    }

    /**
     * @return void
     */
    public function test_unquoted_value_with_hash_but_no_space_is_kept(): void
    {
        $result = $this->parser->parse('KEY=value#notacomment');

        $this->assertSame(['KEY' => 'value#notacomment'], $result);
    }

    // ─── Double-quoted values ─────────────────────────────────────────────────

    /**
     * @return void
     */
    public function test_parses_double_quoted_value(): void
    {
        $result = $this->parser->parse('KEY="hello world"');

        $this->assertSame(['KEY' => 'hello world'], $result);
    }

    /**
     * @return void
     */
    public function test_double_quoted_empty_value(): void
    {
        $result = $this->parser->parse('KEY=""');

        $this->assertSame(['KEY' => ''], $result);
    }

    /**
     * @return void
     */
    public function test_double_quoted_escape_newline(): void
    {
        $result = $this->parser->parse('KEY="line1\nline2"');

        $this->assertSame(['KEY' => "line1\nline2"], $result);
    }

    /**
     * @return void
     */
    public function test_double_quoted_escape_tab(): void
    {
        $result = $this->parser->parse('KEY="col1\tcol2"');

        $this->assertSame(['KEY' => "col1\tcol2"], $result);
    }

    /**
     * @return void
     */
    public function test_double_quoted_escaped_quote(): void
    {
        $result = $this->parser->parse('KEY="say \"hello\""');

        $this->assertSame(['KEY' => 'say "hello"'], $result);
    }

    /**
     * @return void
     */
    public function test_double_quoted_keeps_hash_without_stripping(): void
    {
        $result = $this->parser->parse('KEY="value # not a comment"');

        $this->assertSame(['KEY' => 'value # not a comment'], $result);
    }

    // ─── Single-quoted values ─────────────────────────────────────────────────

    /**
     * @return void
     */
    public function test_parses_single_quoted_value(): void
    {
        $result = $this->parser->parse("KEY='hello world'");

        $this->assertSame(['KEY' => 'hello world'], $result);
    }

    /**
     * @return void
     */
    public function test_single_quoted_empty_value(): void
    {
        $result = $this->parser->parse("KEY=''");

        $this->assertSame(['KEY' => ''], $result);
    }

    /**
     * @return void
     */
    public function test_single_quoted_no_interpolation(): void
    {
        $result = $this->parser->parse("KEY='hello \$WORLD'");

        $this->assertSame(['KEY' => 'hello $WORLD'], $result);
    }

    /**
     * @return void
     */
    public function test_single_quoted_no_escape_sequences(): void
    {
        $result = $this->parser->parse("KEY='line1\\nline2'");

        $this->assertSame(['KEY' => 'line1\\nline2'], $result);
    }

    // ─── Variable interpolation ───────────────────────────────────────────────

    /**
     * @return void
     */
    public function test_interpolates_braced_variable(): void
    {
        $env = "BASE=/var/www\nPATH=\"\${BASE}/app\"";
        $result = $this->parser->parse($env);

        $this->assertSame(['BASE' => '/var/www', 'PATH' => '/var/www/app'], $result);
    }

    /**
     * @return void
     */
    public function test_interpolates_unbraced_variable(): void
    {
        $env = "NAME=World\nGREET=\"Hello \$NAME\"";
        $result = $this->parser->parse($env);

        $this->assertSame(['NAME' => 'World', 'GREET' => 'Hello World'], $result);
    }

    /**
     * @return void
     */
    public function test_escaped_dollar_not_interpolated(): void
    {
        $result = $this->parser->parse('KEY="price is \$5"');

        $this->assertSame(['KEY' => 'price is $5'], $result);
    }

    /**
     * @return void
     */
    public function test_unknown_variable_expands_to_empty_string(): void
    {
        $result = $this->parser->parse('KEY="${UNDEFINED_VAR_EZ_PHP}"');

        $this->assertSame(['KEY' => ''], $result);
    }
}
