<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The six crops the design uses, named once.
 *
 * Aspect ratio is a layout decision, so it belongs to the box, not to the
 * photograph: the same skyline is 16:9 in a hero and 16:10 in a card thumb.
 * Templates name the role ('hero', 'thumb'); nothing in a template names a
 * number, and no call site invents a width/height pair of its own.
 */
final readonly class AspectRatio
{
    private function __construct(
        public string $name,
        public int $width,
        public int $height,
    ) {}

    /**
     * Ratios as the design specifies them. `cover` is 3:4.1 — a real magazine
     * trim, not a rounding of 3:4 — so it is expressed in tenths.
     *
     * @return array<string, array{int, int}>
     */
    public static function all(): array
    {
        return [
            'hero' => [1600, 900],      // 16:9  — hero and featured
            'inline' => [1600, 800],    // 16:8  — article inline imagery
            'card' => [1600, 1000],     // 16:10 — card thumbnails
            'portrait' => [1200, 1500],  // 4:5   — portrait imagery
            'square' => [1000, 1000],   // 1:1   — square thumbs, logos, avatars
            'cover' => [1200, 1640],    // 3:4.1 — magazine cover
        ];
    }

    public static function from(string $name): self
    {
        $all = self::all();

        // An unknown name is a template typo, and silently rendering a 16:9 box
        // would hide it until someone noticed the crop was wrong.
        if (! isset($all[$name])) {
            throw new \InvalidArgumentException(
                "Unknown aspect ratio [{$name}]. Known: ".implode(', ', array_keys($all)).'.'
            );
        }

        [$width, $height] = $all[$name];

        return new self($name, $width, $height);
    }

    /** The Tailwind utility that reserves the box in CSS. */
    public function class(): string
    {
        return 'ratio-'.$this->name;
    }

    /** `16 / 9` as CSS writes it. */
    public function css(): string
    {
        return $this->width.' / '.$this->height;
    }
}
