<?php

declare(strict_types=1);

namespace App\Services\Translation;

use RuntimeException;

/**
 * A provider answered, and the answer is not usable: a protected token went
 * missing, a segment came back empty, or the text is not in the target
 * script. The message is safe to store and show; it never quotes content.
 */
final class TranslationRejected extends RuntimeException {}
