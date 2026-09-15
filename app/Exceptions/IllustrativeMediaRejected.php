<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * An illustrative image was about to be used where a reader would read it as
 * a photograph of the thing it shows.
 */
class IllustrativeMediaRejected extends RuntimeException
{
    public static function forArticle(): self
    {
        return new self(
            'لا يمكن استخدام صورة تعبيرية في مادة تحريرية. '
            .'الصور التعبيرية مسموحة فقط كخلفية في الصفحة الرئيسية.'
        );
    }
}
