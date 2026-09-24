<?php
/**
 * Summary domain state.
 *
 * @package PersianAiSummary
 */

declare(strict_types=1);

namespace PersianAiSummary\Domain;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * Immutable state for one stored post summary.
 */
final class Summary {
	private const SCHEMA_VERSION = 1;
	private const GENERATED      = 'generated';
	private const MANUAL         = 'manual';

	/**
	 * Summary text.
	 *
	 * @var string
	 */
	private string $text;

	/**
	 * Summary origin.
	 *
	 * @var string
	 */
	private string $origin;

	/**
	 * Monotonic revision.
	 *
	 * @var int
	 */
	private int $revision;

	/**
	 * Saved post content hash.
	 *
	 * @var string|null
	 */
	private ?string $source_hash;

	/**
	 * Complete generation input hash.
	 *
	 * @var string|null
	 */
	private ?string $generation_hash;

	/**
	 * Last successful generation time.
	 *
	 * @var DateTimeImmutable|null
	 */
	private ?DateTimeImmutable $generated_at;

	/**
	 * Last successful write time.
	 *
	 * @var DateTimeImmutable
	 */
	private DateTimeImmutable $updated_at;

	/**
	 * Create validated state.
	 *
	 * @param string                 $text            Summary text.
	 * @param string                 $origin          Summary origin.
	 * @param int                    $revision        Positive revision.
	 * @param string|null            $source_hash     Saved content hash.
	 * @param string|null            $generation_hash Generation input hash.
	 * @param DateTimeImmutable|null $generated_at    Generation time.
	 * @param DateTimeImmutable      $updated_at      Update time.
	 * @throws InvalidArgumentException When state is invalid.
	 */
	private function __construct(
		string $text,
		string $origin,
		int $revision,
		?string $source_hash,
		?string $generation_hash,
		?DateTimeImmutable $generated_at,
		DateTimeImmutable $updated_at
	) {
		if ( '' === trim( $text ) ) {
			throw new InvalidArgumentException( 'Summary text must not be empty.' );
		}

		if ( ! in_array( $origin, array( self::GENERATED, self::MANUAL ), true ) ) {
			throw new InvalidArgumentException( 'Summary origin is invalid.' );
		}

		if ( 1 > $revision ) {
			throw new InvalidArgumentException( 'Summary revision must be positive.' );
		}

		self::assert_hash( $source_hash );
		self::assert_hash( $generation_hash );

		$this->text            = $text;
		$this->origin          = $origin;
		$this->revision        = $revision;
		$this->source_hash     = $source_hash;
		$this->generation_hash = $generation_hash;
		$this->generated_at    = $generated_at;
		$this->updated_at      = $updated_at;
	}

	/**
	 * Start the lifecycle with a successful generated summary.
	 *
	 * @param string            $text            Summary text.
	 * @param string            $source_hash     Saved content hash.
	 * @param string            $generation_hash Generation input hash.
	 * @param DateTimeImmutable $generated_at    Generation time.
	 */
	public static function generated(
		string $text,
		string $source_hash,
		string $generation_hash,
		DateTimeImmutable $generated_at
	): self {
		return new self(
			$text,
			self::GENERATED,
			1,
			$source_hash,
			$generation_hash,
			$generated_at,
			$generated_at
		);
	}

	/**
	 * Hydrate a stored record and discard unknown fields.
	 *
	 * @param array<string, mixed> $record Stored record.
	 * @throws InvalidArgumentException When stored state is invalid.
	 */
	public static function from_array( array $record ): self {
		if ( self::SCHEMA_VERSION !== ( $record['schema_version'] ?? null ) ) {
			throw new InvalidArgumentException( 'Summary schema version is invalid.' );
		}

		$text            = $record['text'] ?? null;
		$origin          = $record['origin'] ?? null;
		$revision        = $record['revision'] ?? null;
		$source_hash     = $record['source_hash'] ?? null;
		$generation_hash = $record['generation_hash'] ?? null;

		if ( ! is_string( $text ) || ! is_string( $origin ) || ! is_int( $revision ) ) {
			throw new InvalidArgumentException( 'Summary record types are invalid.' );
		}

		if ( null !== $source_hash && ! is_string( $source_hash ) ) {
			throw new InvalidArgumentException( 'Summary source hash type is invalid.' );
		}

		if ( null !== $generation_hash && ! is_string( $generation_hash ) ) {
			throw new InvalidArgumentException( 'Summary generation hash type is invalid.' );
		}

		return new self(
			$text,
			$origin,
			$revision,
			$source_hash,
			$generation_hash,
			self::parse_time( $record['generated_at'] ?? null, true ),
			self::parse_time( $record['updated_at'] ?? null )
		);
	}

	/**
	 * Create the next manual revision.
	 *
	 * @param string            $text       Edited summary text.
	 * @param DateTimeImmutable $updated_at Update time.
	 */
	public function edit( string $text, DateTimeImmutable $updated_at ): self {
		return new self(
			$text,
			self::MANUAL,
			$this->revision + 1,
			$this->source_hash,
			$this->generation_hash,
			$this->generated_at,
			$updated_at
		);
	}

	/**
	 * Create the next generated revision.
	 *
	 * @param string            $text            Summary text.
	 * @param string            $source_hash     Saved content hash.
	 * @param string            $generation_hash Generation input hash.
	 * @param DateTimeImmutable $generated_at    Generation time.
	 */
	public function regenerate(
		string $text,
		string $source_hash,
		string $generation_hash,
		DateTimeImmutable $generated_at
	): self {
		return new self(
			$text,
			self::GENERATED,
			$this->revision + 1,
			$source_hash,
			$generation_hash,
			$generated_at,
			$generated_at
		);
	}

	/**
	 * Serialize the stable storage record.
	 *
	 * @return array<string, int|string|null>
	 */
	public function to_array(): array {
		return array(
			'schema_version'  => self::SCHEMA_VERSION,
			'text'            => $this->text,
			'origin'          => $this->origin,
			'revision'        => $this->revision,
			'source_hash'     => $this->source_hash,
			'generation_hash' => $this->generation_hash,
			'generated_at'    => $this->generated_at?->format( DateTimeInterface::ATOM ),
			'updated_at'      => $this->updated_at->format( DateTimeInterface::ATOM ),
		);
	}

	/** Get summary text. */
	public function text(): string {
		return $this->text;
	}

	/** Get origin. */
	public function origin(): string {
		return $this->origin;
	}

	/** Get revision. */
	public function revision(): int {
		return $this->revision;
	}

	/** Get source hash. */
	public function source_hash(): ?string {
		return $this->source_hash;
	}

	/** Get generation hash. */
	public function generation_hash(): ?string {
		return $this->generation_hash;
	}

	/** Get formatted generation time. */
	public function generated_at(): ?string {
		return $this->generated_at?->format( DateTimeInterface::ATOM );
	}

	/** Get formatted update time. */
	public function updated_at(): string {
		return $this->updated_at->format( DateTimeInterface::ATOM );
	}

	/**
	 * Validate an optional SHA-256 hash.
	 *
	 * @param string|null $hash Optional hash.
	 * @throws InvalidArgumentException When the hash is invalid.
	 */
	private static function assert_hash( ?string $hash ): void {
		if ( null !== $hash && 1 !== preg_match( '/^[a-f0-9]{64}$/', $hash ) ) {
			throw new InvalidArgumentException( 'Summary hash is invalid.' );
		}
	}

	/**
	 * Parse a strict ISO-8601 timestamp.
	 *
	 * @param mixed $value          Stored value.
	 * @param bool  $nullable       Whether null is valid.
	 * @throws InvalidArgumentException When the timestamp is invalid.
	 */
	private static function parse_time( $value, bool $nullable = false ): ?DateTimeImmutable {
		if ( $nullable && null === $value ) {
			return null;
		}

		if ( ! is_string( $value ) ) {
			throw new InvalidArgumentException( 'Summary timestamp type is invalid.' );
		}

		$time = DateTimeImmutable::createFromFormat( DateTimeInterface::ATOM, $value );

		if ( false === $time || $value !== $time->format( DateTimeInterface::ATOM ) ) {
			throw new InvalidArgumentException( 'Summary timestamp is invalid.' );
		}

		return $time;
	}
}
