## Laravel Opaque ID

Generate short, reversible, opaque identifiers for numeric IDs in Laravel. This package provides a tiny, dependency‑free encoder that maps 32‑bit integers to URL‑safe strings (hex or base64) using a per‑project key. It helps you avoid exposing sequential database IDs in URLs and APIs while keeping lookups fast and simple.

> Note: This is an obfuscation mechanism, not cryptography. Do not use it for protecting sensitive data. It is intended to deter ID enumeration and make URLs less guessable.

### Why opaque IDs?

- **Security**: Discourages enumeration like `/users/1`, `/users/2`, ...
- **Privacy**: Hides record counts and internal sequences.
- **Nicer URLs**: Short, URL‑safe tokens rather than raw integers.

## Opaque ID: Obfuscation for integer IDs

Opaque ID obfuscates integers using a reversible scheme based on a secret key. It aims to hide resource/database IDs from observers when included in public URLs or API responses, without the need for surrogate database keys.

What Opaque IDs look like (illustrative):

| Int ID | Hex      | Base64  |
|--------|----------|---------|
| 1      | 0ae54fa3 | CuVPow  |
| 2      | cbae9d6c | y66dbA  |
| 3      | db2ac148 | 2yrBSA  |

The algorithm is a one‑to‑one integer mapping incorporating a secret key. It's lightweight and compact, at the cost of actual cryptographic security. For real encryption, use proven cryptographic primitives; you won't get such compact ciphertexts with real encryption.

## Requirements

- PHP: 8.2+
- Laravel: 11.x or 12.x (only for service provider auto‑discovery; the encoder itself is framework‑agnostic)

## Installation

```bash
composer require alaminfirdows/laravel-opaque-id
```

Laravel will auto‑discover the service provider. No configuration is required to use the encoder directly.

## Quick start

Instantiate the encoder with your secret project key and choose an encoding mode.

```php
use AlAminFirdows\LaravelOpaqueId\Encoder\OpaqueEncoder;

// Choose a fixed, non‑guessable key for your project (32‑bit integer)
$encoder = new OpaqueEncoder(
    key: 0xA5F00D5,                      // example only — pick your own
    encoding: OpaqueEncoder::ENCODING_BASE64 // or ::ENCODING_HEX / ::ENCODING_INT
);

$public = $encoder->encode(12345); // e.g. "3kq9yQ" (base64) or "00303939" (hex)
$id = $encoder->decode($public);   // 12345
```

Minimal PHP usage examples mirroring the overview above:

```php
use AlAminFirdows\LaravelOpaqueId\Encoder\OpaqueEncoder;

// Hex (8 chars)
$hexEncoder = new OpaqueEncoder(0x3b79db9a, OpaqueEncoder::ENCODING_HEX);
echo $hexEncoder->encode(3);   // e.g. "db2ac148"

// Base64 (6 chars, URL‑safe)
$b64Encoder = new OpaqueEncoder(0x3b79db9a, OpaqueEncoder::ENCODING_BASE64);
echo $b64Encoder->encode(3);   // e.g. "2yrBSA"
echo $b64Encoder->decode('2yrBSA'); // 3
```

### Encoding modes

- `ENCODING_BASE64` (default recommended): 6‑char URL‑safe base64 string
- `ENCODING_HEX`: 8‑char lowercase hex string
- `ENCODING_INT`: reversible integer transform (returns an integer; useful for internal obscurity, generally not for URLs)

## Laravel usage patterns

You can integrate opaque IDs into routing, controllers, and Eloquent models.

### 1) Route parameters

Encode when generating URLs, decode in controllers:

```php
// Generating URLs
$token = app(OpaqueEncoder::class)->encode($post->id);
url("/posts/{$token}");

// Route
Route::get('/posts/{token}', [PostController::class, 'show']);

// Controller
use AlAminFirdows\LaravelOpaqueId\Encoder\OpaqueEncoder;

public function show(string $token, OpaqueEncoder $encoder) {
    $id = $encoder->decode($token);
    $post = Post::findOrFail($id);
    return view('posts.show', compact('post'));
}
```

### 2) Model route keys (pretty URLs)

Expose encoded IDs in URLs by overriding the route key accessors on your model:

```php
use AlAminFirdows\LaravelOpaqueId\Encoder\OpaqueEncoder;

class Post extends Model
{
    public function getRouteKey(): string
    {
        return app(OpaqueEncoder::class)->encode($this->getKey());
    }

    public function resolveRouteBinding($value, $field = null): ?Model
    {
        $id = app(OpaqueEncoder::class)->decode((string) $value);
        return $this->whereKey($id)->first();
    }
}
```

This lets you keep an auto‑incrementing primary key in the database while exposing an opaque token publicly.

### 3) API responses

Hide raw IDs in serialized output and expose an `id` field with the encoded value:

```php
// In your Eloquent model
protected $hidden = ['id'];
protected $appends = ['public_id'];

public function getPublicIdAttribute(): string
{
    return app(OpaqueEncoder::class)->encode($this->getKey());
}
```

## Choosing a key

- Use a fixed, non‑guessable 32‑bit integer per environment (e.g., via `.env`).
- Changing the key invalidates all previously issued tokens.
- Recommended: derive from a random value and clamp into 32‑bit range.

```php
// Example binding with an env‑driven key (e.g., in a service provider)
use AlAminFirdows\LaravelOpaqueId\Encoder\OpaqueEncoder;

$this->app->singleton(OpaqueEncoder::class, function () {
    $key = (int) (intval(env('OPAQUE_ID_KEY', 1469598103)) & 0xFFFFFFFF);
    return new OpaqueEncoder($key, OpaqueEncoder::ENCODING_BASE64);
});
```

Add to your `.env`:

```bash
OPAQUE_ID_KEY=2035692301
```

## Notes and limitations

- Not cryptographic; intended for obfuscation and nicer URLs.
- Works with 32‑bit unsigned integer IDs (typical MySQL `INT`/`BIGINT` within 32‑bit range). If your IDs can exceed 32‑bit, consider a UUID/public_id approach instead.
- Deterministic and reversible: anyone with the key can decode.

## Testing

```bash
composer test
```

## License

MIT
