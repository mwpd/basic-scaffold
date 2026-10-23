<?php
/**
 * MWPD Basic Plugin Scaffold.
 *
 * @package   MWPD\BasicScaffold
 * @author    Alain Schlesser <alain.schlesser@gmail.com>
 * @license   MIT
 * @link      https://www.mwpd.io/
 * @copyright 2019 Alain Schlesser
 */

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\Architecture;

/**
 * What a PHP file declares and does at its top level, read from its tokens.
 *
 * The architecture checks only need to know a file's top-level statements:
 * whether it declares strict types, which classes it declares, and whether
 * anything else runs when it is included. PHP's own tokenizer is enough for
 * that, and keeps the checks free of a parser dependency.
 *
 * @phpstan-type Token array{0: string, 1: string}
 * @phpstan-type Statement array{kind: string, line: int, tokens: array<int, Token>}
 */
final class PhpFile {

	/**
	 * Statements that only declare something.
	 */
	private const DECLARATIONS = [ 'T_CLASS', 'T_INTERFACE', 'T_TRAIT' ];

	/**
	 * Tokens that can start a class declaration before its keyword.
	 */
	private const MODIFIERS = [ 'T_FINAL', 'T_ABSTRACT' ];

	/**
	 * Tokens that carry no meaning for the checks.
	 */
	private const IGNORABLE = [ 'T_WHITESPACE', 'T_COMMENT', 'T_DOC_COMMENT', 'T_OPEN_TAG' ];

	/**
	 * Tokens for `&`, which PHP 8.1 split by what follows them.
	 */
	private const AMPERSANDS = [
		'&',
		'T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG',
		'T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG',
	];

	/**
	 * Tokens that make up a name.
	 */
	private const NAME_PARTS = [ 'T_STRING', 'T_NS_SEPARATOR', 'T_NAME_QUALIFIED', 'T_NAME_FULLY_QUALIFIED' ];

	/**
	 * Path of the file.
	 */
	private string $path;

	/**
	 * Top-level statements, each as its kind, the line it starts on and its
	 * significant tokens.
	 *
	 * @var array<int, Statement>
	 */
	private array $statements = [];

	/**
	 * Instantiate a PhpFile object.
	 *
	 * @param string $path   Path of the file.
	 * @param string $source Source code of the file.
	 */
	public function __construct( string $path, string $source ) {
		$this->path       = $path;
		$this->statements = array_map(
			static fn( array $statement ): array => self::classify( $statement ),
			self::split_statements( $source )
		);
	}

	/**
	 * Read a file.
	 *
	 * @param string $path Path of the file.
	 * @return self The file.
	 */
	public static function from_path( string $path ): self {
		return new self( $path, (string) file_get_contents( $path ) );
	}

	/**
	 * Get the path of the file.
	 *
	 * @return string Path of the file.
	 */
	public function get_path(): string {
		return $this->path;
	}

	/**
	 * Check whether the file declares strict types.
	 *
	 * @return bool Whether the file declares `strict_types=1`.
	 */
	public function declares_strict_types(): bool {
		foreach ( $this->statements as $statement ) {
			if ( $statement['kind'] !== 'T_DECLARE' ) {
				continue;
			}

			$code = strtolower( implode( '', array_column( $statement['tokens'], 1 ) ) );

			if ( strpos( $code, 'strict_types=1' ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get the namespace the file declares.
	 *
	 * @return string Namespace, or an empty string for the global namespace.
	 */
	public function get_namespace(): string {
		foreach ( $this->statements as $statement ) {
			if ( $statement['kind'] === 'T_NAMESPACE' ) {
				return self::read_name( $statement['tokens'], 1 );
			}
		}

		return '';
	}

	/**
	 * Get the classes, interfaces and traits the file declares at its top level.
	 *
	 * @return array<int, array{name: string, line: int}> Fully qualified names
	 *                                                    and lines.
	 */
	public function get_declarations(): array {
		$namespace    = '';
		$declarations = [];

		foreach ( $this->statements as $statement ) {
			if ( $statement['kind'] === 'T_NAMESPACE' ) {
				$namespace = self::read_name( $statement['tokens'], 1 );
			}

			if ( ! in_array( $statement['kind'], self::DECLARATIONS, true ) ) {
				continue;
			}

			$tokens = $statement['tokens'];
			$offset = array_search( $statement['kind'], array_column( $tokens, 0 ), true );
			$name   = self::read_name( $tokens, (int) $offset + 1 );

			$declarations[] = [
				'name' => ltrim( $namespace . '\\' . $name, '\\' ),
				'line' => $statement['line'],
			];
		}

		return $declarations;
	}

	/**
	 * Get the named concrete classes declared at the top level.
	 *
	 * @return array<int, array{name: string, line: int}> Class names and lines.
	 */
	public function get_concrete_classes(): array {
		$namespace = '';
		$classes   = [];
		foreach ( $this->statements as $statement ) {
			if ( $statement['kind'] === 'T_NAMESPACE' ) {
				$namespace = self::read_name( $statement['tokens'], 1 );
			}
			if ( $statement['kind'] !== 'T_CLASS' ) {
				continue;
			}
			$tokens = $statement['tokens'];
			$offset = (int) array_search( 'T_CLASS', array_column( $tokens, 0 ), true );
			if ( in_array( 'T_ABSTRACT', array_column( array_slice( $tokens, 0, $offset ), 0 ), true ) ) {
				continue;
			}
			$classes[] = [
				'name' => ltrim( $namespace . '\\' . self::read_name( $tokens, $offset + 1 ), '\\' ),
				'line' => $statement['line'],
			];
		}

		return $classes;
	}

	/**
	 * Get the top-level statements that do something when the file is included.
	 *
	 * Everything but `declare` directives, `namespace`, `use` and class,
	 * interface and trait declarations counts, including function
	 * declarations, the body of a `declare` block, and any output outside the
	 * PHP tags, even whitespace.
	 *
	 * @return array<int, array{line: int, code: string}> Lines and code.
	 */
	public function get_side_effects(): array {
		$side_effects = [];

		foreach ( $this->statements as $statement ) {
			if ( self::is_directive( $statement )
				|| in_array( $statement['kind'], [ 'T_NAMESPACE', 'T_USE' ], true )
				|| in_array( $statement['kind'], self::DECLARATIONS, true ) ) {
				continue;
			}

			$side_effects[] = [
				'line' => $statement['line'],
				'code' => $statement['kind'] === 'T_INLINE_HTML'
					? 'output outside the PHP tags'
					: self::summarize( $statement['tokens'] ),
			];
		}

		return $side_effects;
	}

	/**
	 * Check whether a statement is a `declare` directive without a body.
	 *
	 * `declare( strict_types=1 );` only configures the file, but
	 * `declare( ticks=1 ) echo 'Tick.';` runs code.
	 *
	 * @param Statement $statement Statement.
	 * @return bool Whether it is a directive without a body.
	 */
	private static function is_directive( array $statement ): bool {
		if ( $statement['kind'] !== 'T_DECLARE' ) {
			return false;
		}

		$kinds = array_column( $statement['tokens'], 0 );
		$end   = array_search( ')', $kinds, true );

		return $end !== false && $kinds[ $end + 1 ] === ';' && count( $kinds ) === $end + 2;
	}

	/**
	 * Check whether the file declares a class, interface, trait or function.
	 *
	 * Declarations nested in other statements, such as a function declared
	 * only if it does not exist yet, count too.
	 *
	 * @return bool Whether the file declares any of them.
	 */
	public function declares_anything(): bool {
		foreach ( $this->statements as $statement ) {
			$tokens = $statement['tokens'];

			foreach ( $tokens as $index => $token ) {
				$previous = $tokens[ $index - 1 ][0] ?? '';
				$next     = $tokens[ $index + 1 ][0] ?? '';

				// A function that returns by reference: `function &name()`.
				if ( in_array( $next, self::AMPERSANDS, true ) ) {
					$next = $tokens[ $index + 2 ][0] ?? '';
				}

				// An anonymous class, a class name constant and a closure do not
				// declare anything.
				if ( in_array( $token[0], [ 'T_INTERFACE', 'T_TRAIT' ], true )
					|| ( $token[0] === 'T_CLASS' && ! in_array( $previous, [ 'T_NEW', 'T_DOUBLE_COLON' ], true ) )
					|| ( $token[0] === 'T_FUNCTION' && $next === 'T_STRING' ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Split source code into its top-level statements.
	 *
	 * A statement ends with a semicolon or with the block it opened, at the
	 * top level or directly inside a braced namespace.
	 *
	 * @param string $source Source code.
	 * @return array<int, Statement> Statements.
	 */
	private static function split_statements( string $source ): array {
		$statements = [];
		$current    = null;
		$depth      = 0;
		$base       = 0;
		$line       = 1;

		foreach ( token_get_all( $source ) as $token ) {
			if ( is_array( $token ) ) {
				$kind = token_name( $token[0] );
				$text = $token[1];
				$line = $token[2];
			} else {
				$kind = $token;
				$text = $token;
			}

			// Output outside the PHP tags is kept, even whitespace: PHP sends it.
			if ( in_array( $kind, self::IGNORABLE, true )
				|| ( $kind === 'T_CLOSE_TAG' && $current === null ) ) {
				continue;
			}

			if ( $current === null ) {
				if ( $kind === '}' && $depth === $base && $base > 0 ) {
					// The end of a braced namespace.
					--$depth;
					$base = 0;
					continue;
				}

				$current = [
					'kind'   => $kind,
					'line'   => $line,
					'tokens' => [],
				];
			}

			$current['tokens'][] = [ $kind, $text ];

			if ( $kind === 'T_INLINE_HTML' && $current['kind'] === 'T_INLINE_HTML' ) {
				$statements[] = $current;
				$current      = null;
				continue;
			}

			if ( in_array( $kind, [ '{', 'T_CURLY_OPEN', 'T_DOLLAR_OPEN_CURLY_BRACES' ], true ) ) {
				++$depth;

				if ( $current['kind'] === 'T_NAMESPACE' && $depth === $base + 1 ) {
					$base         = $depth;
					$statements[] = $current;
					$current      = null;
				}

				continue;
			}

			if ( $kind === '}' ) {
				--$depth;
			}

			if ( $depth === $base
				&& ( $kind === ';' || $kind === '}' || $kind === 'T_CLOSE_TAG' )
				&& ! self::continues_after_block( $current, $kind ) ) {
				$statements[] = $current;
				$current      = null;
			}
		}

		if ( $current !== null ) {
			$statements[] = $current;
		}

		return $statements;
	}

	/**
	 * Name a statement's kind after its keyword.
	 *
	 * `final class Foo` is a class declaration, so a statement that starts with
	 * a modifier takes the kind of the keyword that follows.
	 *
	 * @param Statement $statement Statement.
	 * @return Statement Classified statement.
	 */
	private static function classify( array $statement ): array {
		foreach ( $statement['tokens'] as $token ) {
			if ( ! in_array( $token[0], self::MODIFIERS, true ) ) {
				$statement['kind'] = $token[0];
				break;
			}
		}

		return $statement;
	}

	/**
	 * Check whether a statement goes on after one of its blocks closed.
	 *
	 * A closure assigned to a variable still needs its semicolon.
	 *
	 * @param Statement $statement Statement so far.
	 * @param string    $kind      Token that ended a block.
	 * @return bool Whether the statement continues.
	 */
	private static function continues_after_block( array $statement, string $kind ): bool {
		return $kind === '}'
			&& ! in_array( $statement['kind'], array_merge( self::DECLARATIONS, self::MODIFIERS ), true )
			&& ! in_array(
				$statement['kind'],
				[ 'T_FUNCTION', 'T_IF', 'T_FOR', 'T_FOREACH', 'T_WHILE', 'T_SWITCH', 'T_TRY', 'T_DECLARE' ],
				true
			);
	}

	/**
	 * Read a name from the tokens of a statement.
	 *
	 * @param array<int, Token> $tokens Tokens of the statement.
	 * @param int               $offset Position the name starts at.
	 * @return string Name.
	 */
	private static function read_name( array $tokens, int $offset ): string {
		$name  = '';
		$index = $offset;
		while ( isset( $tokens[ $index ] ) && in_array( $tokens[ $index ][0], self::NAME_PARTS, true ) ) {
			$name .= $tokens[ $index ][1];
			++$index;
		}

		return trim( $name, '\\' );
	}

	/**
	 * Summarize a statement for a message.
	 *
	 * @param array<int, Token> $tokens Tokens of the statement.
	 * @return string The statement's start, on one line.
	 */
	private static function summarize( array $tokens ): string {
		$code = trim( (string) preg_replace( '/\s+/', ' ', implode( ' ', array_column( $tokens, 1 ) ) ) );

		return strlen( $code ) > 60 ? substr( $code, 0, 57 ) . '...' : $code;
	}
}
